"use client";

import { cn } from "@/lib/utils";
import { DotPattern } from "@/components/ui/dot-pattern";

function Panoramic() {
  return (
    <div className="max-w-7xl mx-auto w-full">
      <div className="relative flex h-[500px] w-full flex-col items-center justify-center max-w-4xl mx-auto gap-8 text-center overflow-hidden rounded-lg font-bold px-6">
        <h3 className="text-4xl">
          Scopri <span className="text-primary">Spreetzitt</span>
        </h3>
        <p className="text-2xl hidden md:block">
          Spreetzitt è la piattaforma all’avanguardia che centralizza e
          semplifica la gestione dei ticket, dei problemi e delle richieste di
          supporto IT.
        </p>
        <p className="text-2xl">
          La soluzione ideale per ridurre i tempi di risoluzione e aumentare la
          soddisfazione degli utenti, rendendo il supporto IT più agile e
          trasparente.
        </p>
        <DotPattern
          className={cn(
            "[mask-image:radial-gradient(300px_circle_at_center,white,transparent)]"
          )}
        />
      </div>
    </div>
  );
}

export default Panoramic;
