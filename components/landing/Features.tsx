import {
  ChartLineIcon,
  Clock,
  Computer,
  SquarePenIcon,
  Ticket,
  Users,
} from "lucide-react";

import { BentoGrid, BentoGridItem } from "../ui/bento-grid";

type FeatureItem = {
  title: string;
  description: string;
  icon: React.ReactNode;
  classname: string;
};

const items: FeatureItem[] = [
  {
    title: "Gestione dei Ticket (Request e Problem)",
    description:
      "La piattaforma consente di centralizzare tutte le richieste di supporto, distinguendo tra ticket di tipo request e problem. Questo permette di trattare in modo specifico le richieste di servizio (request) e le segnalazioni di problemi (problem), garantendo che ogni tipologia segua il percorso più adeguato per la risoluzione.",
    icon: <Ticket />,
    classname: "md:col-span-2",
  },
  {
    title: "Webform Personalizzabili",
    description:
      "Grazie a webform configurabili, è possibile definire le tipologie di ticket in base alle esigenze aziendali, raccogliendo tutte le informazioni necessarie per accelerare il processo di assegnazione e risoluzione.",
    icon: <SquarePenIcon />,
    classname: "md:col-span-1",
  },
  {
    title: "Gestione della SLA",
    description:
      "Imposta e monitora i livelli di servizio (SLA) per assicurare che ogni ticket venga gestito entro i tempi stabiliti. Spreetzitt invia notifiche e alert in caso di violazione degli SLA, permettendo interventi proattivi e mantenendo elevati standard di qualità nel supporto IT.",
    icon: <Clock />,
    classname: "md:col-span-1",
  },
  {
    title: "Gestione del Personale",
    description:
      "Organizza il team di supporto in gruppi dedicati, in modo da assegnare automaticamente i ticket agli operatori più competenti. La piattaforma permette di configurare le squadre e monitorare il carico di lavoro, garantendo una distribuzione equilibrata e una risposta tempestiva alle richieste.",
    icon: <Users />,
    classname: "md:col-span-2",
  },
  {
    title: "Reportizzazione delle Attività",
    description:
      "Accedi a strumenti avanzati di reportistica che forniscono una visione dettagliata delle performance del service desk. I report permettono di analizzare l'andamento delle attività, identificare eventuali criticità e intervenire per ottimizzare i processi e migliorare continuamente il servizio.",
    icon: <ChartLineIcon />,
    classname: "md:col-span-2",
  },
  {
    title: "Associazione e Gestione dell'Hardware agli Utenti",
    description:
      "Integra la gestione degli asset IT associando gli hardware agli utenti. Questa funzionalità centralizza le informazioni relative ai dispositivi, facilitando il monitoraggio, la manutenzione e la gestione dei costi, per avere sempre sotto controllo il parco tecnologico aziendale.",
    icon: <Computer />,
    classname: "md:col-span-1",
  },
];

const Skeleton = () => (
  <div className="flex flex-1 w-full h-full min-h-[6rem] rounded-xl bg-gradient-to-br from-neutral-200 dark:from-neutral-900 dark:to-neutral-800 to-neutral-100"></div>
);

function Features() {
  return (
    <BentoGrid className="max-w-7xl mx-auto md:auto-rows-[20rem] px-4 md:px-0">
      {items.map((item, i) => (
        <BentoGridItem
          key={i}
          title={item.title}
          description={item.description}
          icon={item.icon}
          header={<Skeleton />}
          className={item.classname}
        />
      ))}
    </BentoGrid>
  );
}

export default Features;
