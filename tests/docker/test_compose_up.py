import importlib.util
import io
from pathlib import Path
import signal
import unittest
from unittest.mock import Mock, patch


spec = importlib.util.spec_from_file_location(
    "compose_up", Path(__file__).resolve().parents[2] / "docker/compose-up.py",
)
compose_up = importlib.util.module_from_spec(spec)
spec.loader.exec_module(compose_up)


class ComposeUpTest(unittest.TestCase):
    @patch.object(compose_up, "privileged")
    @patch.object(compose_up.subprocess, "run", return_value=Mock(returncode=0, stdout="123\n"))
    @patch.object(compose_up.shutil, "which", return_value="/usr/bin/lsof")
    def test_inspects_user_owned_listener_without_sudo(self, which, run, privileged):
        self.assertEqual(compose_up.listener_pids(6379), {123})
        privileged.assert_not_called()

    @patch.object(compose_up, "privileged", return_value=["sudo", "lsof"])
    @patch.object(compose_up, "output", return_value="LISTEN 0 511 0.0.0.0:6379")
    @patch.object(compose_up.subprocess, "run")
    @patch.object(compose_up.shutil, "which", return_value="/usr/bin/lsof")
    def test_requests_privileges_when_listener_belongs_to_another_user(self, which, run, output, privileged):
        run.side_effect = [Mock(returncode=1, stdout=""), Mock(returncode=0, stdout="456\n")]
        self.assertEqual(compose_up.listener_pids(6379), {456})
        privileged.assert_called_once()

    def test_parses_docker_port_errors(self):
        for address in ("0.0.0.0", "[::]"):
            self.assertEqual(compose_up.conflicting_port(
                f"failed to bind host port {address}:7700/tcp: address already in use"
            ), 7700)
        self.assertEqual(compose_up.conflicting_port(
            "Bind for 0.0.0.0:3306 failed: port is already allocated"
        ), 3306)
        self.assertIsNone(compose_up.conflicting_port("database unhealthy"))
        self.assertIsNone(compose_up.conflicting_port(
            "failed to bind host port 0.0.0.0:7700/udp: address already in use"
        ))

    @patch.object(compose_up, "listener_pids")
    @patch.object(compose_up.subprocess, "run")
    @patch.object(compose_up, "output")
    def test_preserves_project_container(self, output, run, listeners):
        output.side_effect = ["abc", "abc", '[{"Id":"abcdef", "NetworkSettings":{"Ports":{"7700/tcp":[{"HostPort":"7700"}]}}}]']
        with self.assertRaisesRegex(RuntimeError, "questo progetto"):
            compose_up.free_port(7700)
        run.assert_not_called()
        listeners.assert_not_called()

    @patch.object(compose_up.time, "sleep")
    @patch.object(compose_up, "listener_pids", return_value=set())
    @patch.object(compose_up.subprocess, "run")
    @patch.object(compose_up, "output")
    def test_stops_external_container_only_for_matching_host_port(self, output, run, listeners, sleep):
        output.side_effect = ["project", "external unrelated", '''[
            {"Id":"external", "Name":"/other_meili", "NetworkSettings":{"Ports":{"7700/tcp":[{"HostPort":"7700"}]}}},
            {"Id":"unrelated", "Name":"/another", "NetworkSettings":{"Ports":{"7700/tcp":[{"HostPort":"7701"}]}}}
        ]''']
        compose_up.free_port(7700)
        run.assert_called_once_with(["docker", "stop", "external"], check=True)

    @patch.object(compose_up.time, "sleep")
    @patch.object(compose_up.os, "kill")
    @patch.object(compose_up, "listener_pids")
    @patch.object(compose_up, "output")
    def test_terminates_listener_and_forces_only_original_pids(self, output, listeners, kill, sleep):
        output.side_effect = ["", "", "meilisearch"]
        listeners.side_effect = [{123}] + [{123, 456}] * 5 + [set()]
        compose_up.free_port(7700)
        self.assertEqual(kill.call_args_list, [
            unittest.mock.call(123, signal.SIGTERM),
            unittest.mock.call(123, signal.SIGKILL),
        ])

    @patch.object(compose_up.os, "kill")
    @patch.object(compose_up, "listener_pids", return_value={123})
    @patch.object(compose_up, "output", side_effect=["", "", "docker-proxy"])
    def test_does_not_kill_docker_proxy(self, output, listeners, kill):
        with self.assertRaisesRegex(RuntimeError, "docker-proxy"):
            compose_up.free_port(7700)
        kill.assert_not_called()

    def test_retries_multiple_conflicts_and_stops_on_repeated_port(self):
        error = "failed to bind host port 0.0.0.0:{}/tcp: address already in use\n"
        for ports, expected_status in (([7700, 3306, None], 0), ([7700, 7700], 1), ([None], 1)):
            processes = [Mock(stdout=io.StringIO(error.format(port) if port else "other output")) for port in ports]
            for process, port in zip(processes, ports):
                process.wait.return_value = 0 if port is None and expected_status == 0 else 1
            with patch.object(compose_up, "prepare_services"), patch.object(compose_up.subprocess, "Popen", side_effect=processes), patch.object(compose_up, "free_port") as free:
                self.assertEqual(compose_up.main(), expected_status)
                self.assertEqual([call.args[0] for call in free.call_args_list], list(dict.fromkeys(p for p in ports if p)))

    @patch.object(compose_up.subprocess, "run")
    @patch.object(compose_up, "free_port")
    @patch.object(compose_up, "listener_pids", return_value={123})
    @patch.object(compose_up, "output")
    def test_frees_redis_port_and_recreates_networkless_running_container(self, output, listeners, free, run):
        output.side_effect = [
            '{"services":{"redis":{"networks":{"default":null},"ports":[{"published":"6379","protocol":"tcp"}]}}}',
            'redis-id',
            '[{"State":{"Running":true},"Config":{"Labels":{"com.docker.compose.service":"redis"}},"NetworkSettings":{"Networks":{},"Ports":{}}}]',
        ]
        compose_up.prepare_services()
        listeners.assert_called_once_with(6379)
        free.assert_called_once_with(6379)
        run.assert_called_once_with([
            'docker', 'compose', 'up', '-d', '--no-deps', '--force-recreate', 'redis',
        ], check=True)

    @patch.object(compose_up.subprocess, "run")
    @patch.object(compose_up, "free_port")
    @patch.object(compose_up, "listener_pids")
    @patch.object(compose_up, "output")
    def test_preserves_connected_project_container_and_its_published_port(self, output, listeners, free, run):
        output.side_effect = [
            '{"services":{"redis":{"networks":{"default":null},"ports":[{"published":"6379","protocol":"tcp"}]}}}',
            'redis-id',
            '[{"State":{"Running":true},"Config":{"Labels":{"com.docker.compose.service":"redis"}},"NetworkSettings":{"Networks":{"default":{"EndpointID":"endpoint"}},"Ports":{"6379/tcp":[{"HostPort":"6379"}]}}}]',
        ]
        compose_up.prepare_services()
        listeners.assert_not_called()
        free.assert_not_called()
        run.assert_not_called()


if __name__ == "__main__":
    unittest.main()
