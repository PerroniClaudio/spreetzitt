import { Button } from "../ui/button";
import { Meteors } from "@/components/ui/meteors";

function Hero() {
  return (
    <div className="flex flex-col items-center gap-4 justify-center py-32 pb-8 max-w-7xl mx-auto">
      <Meteors number={30} />
      <h1 className="text-6xl font-semibold text-center">
        <span className="text-primary">Spreetziit</span>
      </h1>
      <h3 className="text-4xl font-semibold text-center">
        La Soluzione Smart per il Supporto IT
      </h3>
      <div className="lg:w-1/2">
        <p className="text-2xl text-center mt-4">
          Centralizza, automatizza e risolvi ogni richiesta in un&apos;unica
          piattaforma intuitiva, per un supporto IT rapido ed efficiente.
        </p>
      </div>
      <Button>Richiedi una demo</Button>
    </div>
  );
}

export default Hero;
