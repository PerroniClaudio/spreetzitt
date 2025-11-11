@include('components.style')

<style>
    /* FONT OVERRIDE AGGRESSIVO PER DOMPDF */
    * {
        font-family: "Inter", sans-serif !important;
    }
    
    /* Ticket container specifico per project reports */
    .ticket-container {
        font-size: 0.75rem !important;
        line-height: 1rem !important;
    }

    /* Assicura che tutto il testo usi font sicuri per PDF */
    body, * {
    }
    
    /* Forzatura specifica per le classi problematiche */
    .font-semibold,
    .stat-label, 
    .summary-label,
    .main-header {
    }
    
    /* Stili aggiuntivi specifici per i report di progetto */
    
    /* Miglioramento tabelle con bordi più definiti */
    .project-table {
        width: 100%;
        border: 1px solid #353131;
        border-collapse: collapse;
        font-size: 0.7rem;
    }
    
    .project-table th,
    .project-table td {
        border: 1px solid #353131;
        padding: 0.5rem;
        text-align: left;
    }
    
    .project-table th {
        background-color: #f3f4f6;
        font-weight: 600;
    }
    
    /* Sezioni informative miglioriate */
    .project-info-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    
    .project-info-table td {
        padding: 0.25rem 0;
        vertical-align: top;
    }
    
    /* Box statistiche */
    .stats-container {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        border: 1px solid #e5e7eb;
    }
    
    .stats-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: space-between;
        font-size: 0.8rem;
    }
    
    /* Grid 2x2 per 4 elementi in 2 righe - Float layout per DomPDF */
    .stats-grid-2x2 {
        overflow: hidden;
        font-size: 0.8rem;
    }
    
    .stat-box {
        float: left;
        width: 48%;
        margin-right: 2%;
        margin-bottom: 0.5rem;
        text-align: center;
        padding: 0.5rem;
        box-sizing: border-box;
    }
    
    .stat-box:nth-child(2n) {
        margin-right: 0;
    }
    
    /* Clearfix per pulire i float */
    .stats-grid-2x2::after {
        content: "";
        display: table;
        clear: both;
    }
    
    .stats-summary-grid::after {
        content: "";
        display: table;
        clear: both;
    }
    
    .stat-label {
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
    }
    
    /* Versione più piccola per i numeri */
    .stat-value-small {
        font-size: 1.2rem;
        font-weight: 700;
        line-height: 1;
        margin-top: 0.25rem;
    }
    
    .stat-value.primary { color: #1f2937; }
    .stat-value.success { color: #059669; }
    .stat-value.danger { color: #dc2626; }
    .stat-value.warning { color: #f59e0b; }
    .stat-value.info { color: #2563eb; }
    
    /* Colori per la versione piccola */
    .stat-value-small.primary { color: #1f2937; }
    .stat-value-small.success { color: #059669; }
    .stat-value-small.danger { color: #dc2626; }
    .stat-value-small.warning { color: #f59e0b; }
    .stat-value-small.info { color: #2563eb; }
    
    /* Chart containers */
    .chart-container {
        width: 48%;
        text-align: center;
        margin-bottom: 1rem;
    }
    
    .chart-container img {
        max-width: 100%;
        height: auto;
        max-height: 250px;
    }
    
    /* Status badges */
    .status-badge {
        padding: 0.125rem 0.5rem;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        color: white;
        display: inline-block;
    }
    
    .status-badge.success { background-color: #22c55e; }
    .status-badge.warning { background-color: #f59e0b; }
    .status-badge.danger { background-color: #dc2626; }
    .status-badge.info { background-color: #2563eb; }
    
    /* Type badges */
    .type-badge {
        padding: 0.125rem 0.25rem;
        border-radius: 3px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .type-badge.incident {
        background-color: #fef2f2;
        color: #dc2626;
    }
    
    .type-badge.request {
        background-color: #eff6ff;
        color: #2563eb;
    }
    
    /* Billability badges */
    .billable-badge {
        padding: 0.125rem 0.25rem;
        border-radius: 3px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .billable-badge.yes {
        background-color: #f0fdf4;
        color: #16a34a;
    }
    
    .billable-badge.no {
        background-color: #fef2f2;
        color: #dc2626;
    }
    
    /* Ticket details */
    .ticket-detail-card {
        margin-bottom: 1.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .ticket-detail-header {
        border-bottom: 2px solid #e5e7eb;
        padding: 0.75rem;
        background-color: #f9fafb;
    }
    
    .ticket-detail-content {
        padding: 0.75rem;
    }
    
    .ticket-id-badge {
        background-color: #e73029;
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    /* Data sections */
    .data-section {
        padding: 0.75rem;
        border-radius: 4px;
        margin-bottom: 1rem;
        border: 1px solid #e5e7eb;
    }
    
    .data-section.webform {
        background-color: #f8f9fa;
        border-left: 4px solid #6b7280;
    }
    
    .data-section.description {
        background-color: #f0fdf4;
        border-left: 4px solid #16a34a;
    }
    
    .data-section.messages {
        background-color: #f0f9ff;
        border-left: 4px solid #3b82f6;
    }
    
    .data-section.closing {
        background-color: #f0fdf4;
        border-left: 4px solid #16a34a;
    }
    
    .data-section-title {
        margin: 0 0 0.5rem 0;
        font-size: 0.9rem;
        color: #374151;
        font-weight: 600;
    }
    
    /* Message items */
    .message-item {
        margin-bottom: 0.5rem;
        padding: 0.5rem;
        background-color: white;
        border-radius: 3px;
        font-size: 0.75rem;
        border: 1px solid #e5e7eb;
    }
    
    .message-header {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
        font-size: 0.7rem;
    }
    
    .message-content {
        color: #4b5563;
        line-height: 1.4;
    }
    
    /* Info grid */
    .info-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1rem;
        font-size: 0.8rem;
    }
    
    .info-item {
        min-width: 150px;
    }
    
    .info-label {
        font-weight: 600;
        color: #6b7280;
    }
    
    .info-value {
        color: #1f2937;
    }
    
    /* Assicura Inter per tutte le classi di testo specifiche */
    /* .data-section-title,
    .data-section-content,
    .message-header,
    .message-content,
    .summary-label,
    .summary-value,
    .stat-label,
    .stat-value,
    .section-header,
    .main-header,
    .sub-header,
    div, p, span, tr, td, th {
    } */
    
    /* Footer */
    .report-footer {
        margin-top: 2rem;
        text-align: center;
        font-size: 0.7rem;
        color: #6b7280;
        border-top: 1px solid #e5e7eb;
        padding-top: 1rem;
    }
    
    /* Responsive adjustments */
    @media print {
        .chart-container {
            width: 100%;
            margin-bottom: 0.5rem;
        }
        
        .stats-grid {
            gap: 0.5rem;
        }
    }
    
    /* Utility classes */
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-muted { color: #6b7280; }
    .font-semibold { 
        font-weight: 600; 
    }
    .font-bold { font-weight: 700; }
    .mb-1 { margin-bottom: 0.25rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-4 { margin-bottom: 1rem; }
    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-4 { margin-top: 1rem; }

    /* Additional layout utilities */
    /* Date info table styling */
    .date-info-table {
        margin: auto;
        font-size: 0.75rem;
        width: fit-content;
    }
    .date-info-table table {
        width: 100%;
    }
    .date-label {
        width: 25%;
        text-align: right;
        padding-right: 1rem;
    }
    .date-value {
        width: 25%;
        text-align: left;
    }

    /* Chart containers */
    .charts-container {
        width: 100%;
        overflow: hidden;
        margin-bottom: 1rem;
    }
    .chart-item {
        width: 48%;
        float: left;
        text-align: center;
        margin-right: 2%;
    }
    .chart-item:last-child {
        margin-right: 0;
    }
    .chart-image {
        max-width: 100%;
        height: auto;
        max-height: 250px;
    }
    
    /* Clearfix for floating elements */
    .charts-container::after {
        content: "";
        display: table;
        clear: both;
    }

    /* Spacer */
    .spacer {
        height: 1rem;
    }

    /* Center align */
    .center-align {
        text-align: center;
    }
    
    /* Logo styling */
    .logo-image {
        width: auto;
        height: 38px;
        position: absolute;
        top: 0;
        left: 0;
    }

    /* Table cell styling */
    .table-cell {
        width: 20%;
    }

    /* Summary statistics styling */
    .stats-summary-container {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 6px;
    }
    
    .stats-summary-grid {
        overflow: hidden;
        font-size: 0.8rem;
        margin-bottom: 1rem;
    }
    
    .summary-item {
        float: left;
        width: 30%;
        margin-right: 3.33%;
        text-align: center;
        padding: 0.5rem;
        box-sizing: border-box;
    }
    
    .summary-item:nth-child(3n) {
        margin-right: 0;
    }
    
    .summary-item:last-child {
        margin-right: 0;
    }
    
    .summary-label {
        font-weight: 600;
        color: #6b7280;
    }
    
    .summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
    }
    
    .summary-value.green {
        color: #059669;
    }
    
    .summary-value.red {
        color: #dc2626;
    }
    
    .summary-value.orange {
        color: #f59e0b;
    }
    
    .summary-value.gray {
        color: #6b7280;
    }
    
    .summary-value.blue {
        color: #3b82f6;
    }
    
    .summary-value.purple {
        color: #7c3aed;
    }
    /* Table styling */
    /* .table-row-even {
        background-color: #f9fafb;
    } */
    
    /* No data message */
    .no-data-message {
        text-align: center;
        color: #6b7280;
        font-style: italic;
        padding: 2rem;
    }
    
    /* Margin top utility */
    .mt-8 {
        margin-top: 2rem;
    }
    
    /* Ticket header */
    .ticket-header {
        margin: 0;
        font-size: 1rem;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    /* Small italic text */
    .small-italic {
        font-size: 0.7rem;
        font-style: italic;
    }
    
    /* Data section content */
    .data-section-content {
        font-size: 0.75rem;
        color: #4b5563;
    }
    
    /* Webform specific content */
    .webform-content {
        font-size: 0.75rem;
        line-height: 1.4;
    }
    
    .webform-item {
        margin-bottom: 0.25rem;
    }
</style>