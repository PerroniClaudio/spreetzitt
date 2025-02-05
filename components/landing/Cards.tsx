import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

import { ClipboardCheck, Computer, MonitorCheck } from "lucide-react";

function Cards() {
  return (
    <div className="grid lg:grid-cols-3 gap-4 px-4">
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <MonitorCheck />
              <span>Interfaccia Intuitiva</span>
            </div>
          </CardTitle>
          <CardDescription>
            Un design user-friendly che permette agli utenti di registrare e
            monitorare le proprie richieste in autonomia, migliorando la
            trasparenza e l’efficienza del servizio.
          </CardDescription>
        </CardHeader>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <ClipboardCheck />
              <span>Reportistica Avanzata</span>
            </div>
          </CardTitle>
          <CardDescription>
            Visualizza in tempo reale le performance del tuo supporto IT grazie
            a dashboard personalizzate e report dettagliati, per intervenire
            tempestivamente e ottimizzare i processi.
          </CardDescription>
        </CardHeader>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <Computer />
              <span>Servizio ITAM</span>
            </div>
          </CardTitle>
          <CardDescription>
            Gestisci l'intero ciclo di vita dei tuoi asset IT: da inventario a
            manutenzione, Spreetzitt centralizza le informazioni e ottimizza
            costi e performance.
          </CardDescription>
        </CardHeader>
      </Card>
    </div>
  );
}

export default Cards;
