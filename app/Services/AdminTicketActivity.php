<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketStage;
use App\Models\TicketStatusUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminTicketActivity
{
    public function search(int $adminId, CarbonImmutable $start, CarbonImmutable $endExclusive, int $perPage = 25): LengthAwarePaginator
    {
        $closedStageId = TicketStage::withTrashed()->where('system_key', 'closed')->value('id');
        $closedBeforePeriod = static function (Builder $updates) use ($start, $closedStageId) {
            $updates->where('created_at', '<', $start)
                ->where(function (Builder $closing) use ($closedStageId) {
                    $closing->where('type', 'closing');
                    if ($closedStageId !== null) {
                        $closing->orWhere('new_stage_id', $closedStageId);
                    }
                });
        };
        $assigned = static function (Builder $query) use ($adminId, $endExclusive, $closedBeforePeriod) {
            $query->where('admin_user_id', $adminId)
                ->where('tickets.created_at', '<', $endExclusive)
                ->whereDoesntHave('statusUpdates', $closedBeforePeriod);
        };
        $interactions = ['messages', 'statusUpdates', 'ticketLogs', 'ticketReminders'];
        $byAdminInPeriod = static function (Builder $query) use ($adminId, $start, $endExclusive) {
            $table = $query->getModel()->getTable();
            $query->reorder()->where($table.'.user_id', $adminId)
                ->where($table.'.created_at', '>=', $start)
                ->where($table.'.created_at', '<', $endExclusive);
        };

        $query = Ticket::query()
            ->select(['id', 'description', 'company_id', 'type_id', 'stage_id', 'admin_user_id', 'created_at', 'actual_processing_time'])
            ->with(['company:id,name', 'ticketType:id,name', 'stage:id,name'])
            ->withMax(['statusUpdates as closed_at' => function (Builder $updates) use ($closedStageId) {
                $updates->where(function (Builder $closing) use ($closedStageId) {
                    $closing->where('type', 'closing');
                    if ($closedStageId !== null) {
                        $closing->orWhere('new_stage_id', $closedStageId);
                    }
                });
            }], 'created_at')
            ->withCasts(['closed_at' => 'datetime'])
            ->where(function (Builder $query) use ($assigned, $interactions, $byAdminInPeriod) {
                $query->where($assigned);
                foreach ($interactions as $relation) {
                    $query->orWhereHas($relation, $byAdminInPeriod);
                }
            })
            ->withExists(['statusUpdates as closed_before_period' => $closedBeforePeriod]);
        foreach ($interactions as $relation) {
            if ($relation !== 'statusUpdates') {
                $query->withCount([$relation => $byAdminInPeriod]);
            }
        }
        $tickets = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();

        // Group update types in one query for the current page; never load message bodies.
        $updates = TicketStatusUpdate::query()
            ->whereIn('ticket_id', $tickets->getCollection()->pluck('id'));
        $byAdminInPeriod($updates);
        $updates = $updates->selectRaw('ticket_id, type, COUNT(*) as total')
            ->groupBy('ticket_id', 'type')->orderBy('type')->get()->groupBy('ticket_id');
        $updateLabels = [
            'time' => 'Modifiche tempo effettivo', 'note' => 'Note interne',
            'status' => 'Modifiche stato', 'closing' => 'Chiusure',
            'assign' => 'Assegnazioni', 'group_assign' => 'Assegnazioni gruppo',
            'billing' => 'Modifiche fatturazione', 'work_mode' => 'Modifiche modalità di lavoro',
            'sla' => 'Modifiche SLA', 'blame' => 'Modifiche valutazione',
            'scheduling' => 'Modifiche attività programmata',
            'project' => 'Collegamenti progetto', 'project_update' => 'Modifiche progetto',
        ];
        $tickets->getCollection()->each(function (Ticket $ticket) use ($adminId, $endExclusive, $updates, $updateLabels) {
            $reasons = [];
            if ((int) $ticket->admin_user_id === $adminId && $ticket->created_at < $endExclusive && ! $ticket->closed_before_period) {
                $reasons[] = ['type' => 'assigned', 'label' => 'Assegnato all’admin e aperto nel periodo'];
            }
            if ($ticket->messages_count) {
                $reasons[] = ['type' => 'message', 'label' => 'Messaggi', 'count' => $ticket->messages_count];
            }
            foreach ($updates->get($ticket->id, collect()) as $update) {
                $reasons[] = ['type' => 'update:'.$update->type, 'label' => $updateLabels[$update->type] ?? 'Altri aggiornamenti', 'count' => (int) $update->total];
            }
            foreach (['ticket_logs_count' => 'Log ticket', 'ticket_reminders_count' => 'Promemoria creati'] as $count => $label) {
                if ($ticket->$count) {
                    $reasons[] = ['type' => $count, 'label' => $label, 'count' => $ticket->$count];
                }
            }
            $ticket->setAttribute('inclusion_reasons', $reasons);
            $ticket->makeHidden(['closed_before_period', 'messages_count', 'status_updates_count', 'ticket_logs_count', 'ticket_reminders_count']);
        });

        return $tickets;
    }
}
