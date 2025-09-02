<?php

use App\Http\Controllers\BrandController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\TicketStatusUpdateController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\OldTicketController;
use App\Http\Controllers\TwoFactorChallengeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get(
        '/user',
        function (Request $request) {
            return $request->user();
        }
    );

    Route::post('/onboarding', [App\Http\Controllers\UserController::class, "onboarding"]);
    Route::patch('/user/profile', [App\Http\Controllers\UserController::class, "updateProfile"]);
    Route::patch('/user/password', [App\Http\Controllers\UserController::class, "updatePassword"]);

    Route::get(
        '/user/all',
        [App\Http\Controllers\UserController::class, "allUsers"]
    );


    Route::post(
        '/user',
        [App\Http\Controllers\UserController::class, "store"]
    );


    Route::patch(
        '/user',
        [App\Http\Controllers\UserController::class, "update"]
    );

    Route::delete(
        '/user/{id}',
        [App\Http\Controllers\UserController::class, "destroy"]
    );

    Route::get(
        '/user/{id}/enable',
        [App\Http\Controllers\UserController::class, "enable"]
    );

    Route::get(
        '/user/alladmins-ids',
        [App\Http\Controllers\UserController::class, "adminsIds"]
    );

    Route::get(
        '/user/alladmins',
        [App\Http\Controllers\UserController::class, "allAdmins"]
    );

    Route::get(
        '/user/{id}/get-name',
        [App\Http\Controllers\UserController::class, "getName"]
    );

    Route::get(
        '/user/frontend/logo',
        [App\Http\Controllers\UserController::class, "getFrontendLogoUrl"]
    );

    Route::get(
        '/user/export-template',
        [App\Http\Controllers\UserController::class, "exportTemplate"]
    );

    Route::post(
        '/user/import',
        [App\Http\Controllers\UserController::class, "importUsers"]
    );
});


Route::middleware(['auth:sanctum'])->get('/user-test', [App\Http\Controllers\UserController::class, "ticketTypes"]);


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post(
        "/ticket/{ticket_id}/message",
        [TicketMessageController::class, "store"]
    );

    Route::get(
        "/ticket/{ticket_id}/messages",
        [TicketMessageController::class, "index"]
    );
});



Route::middleware(['auth:sanctum'])->group(function () {
    Route::post(
        "/ticket/{ticket_id}/status-updates",
        [TicketStatusUpdateController::class, "store"]
    );

    Route::get(
        "/ticket/{ticket_id}/status-updates",
        [TicketStatusUpdateController::class, "index"]
    );
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Route::resource('companies', App\Http\Controllers\CompanyController::class);
    Route::get(
        "/companies",
        [CompanyController::class, "index"]
    );

    Route::get(
        "/companies/{id}",
        [CompanyController::class, "show"]
    );

    Route::post(
        "/companies",
        [CompanyController::class, "store"]
    );

    Route::delete(
        "/companies/{id}",
        [CompanyController::class, "destroy"]
    );

    Route::patch(
        "/companies",
        [CompanyController::class, "update"]
    );

    Route::get(
        "/companies/{company}/offices",
        [CompanyController::class, "offices"]
    );

    Route::get(
        "/companies/{company}/admins",
        [CompanyController::class, "admins"]
    );

    Route::get(
        "/companies/{company}/allusers",
        [CompanyController::class, "allusers"]
    );

    Route::get(
        "/companies/{company}/ticket-types",
        [CompanyController::class, "ticketTypes"]
    );

    Route::get(
        "/companies/{company}/brands",
        [CompanyController::class, "brands"]
    );

    Route::get(
        "/companies/{company}/frontend-logo",
        [CompanyController::class, "getFrontendLogoUrl"]
    );

    Route::get("/companies/{company}/tickets", [CompanyController::class, "tickets"]);

    Route::get("/companies/{company}/weekly-times", [CompanyController::class, "getWeeklyTimes"]);
    Route::post("/companies/{company}/weekly-times", [CompanyController::class, "editWeeklyTime"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('offices', App\Http\Controllers\OfficeController::class);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('ticket', App\Http\Controllers\TicketController::class);

    Route::get("/old-ticket-search", [App\Http\Controllers\OldTicketController::class, "search"]);
    Route::get("/ticket-search", [App\Http\Controllers\TicketController::class, "search"]);


    Route::get(
        "/data-owner/ticket/{ticket}",
        [App\Http\Controllers\TicketController::class, "show"]
    );

    Route::post(
        "/ticketmassive",
        [App\Http\Controllers\TicketController::class, "storeMassive"]
    );

    Route::get(
        "/ticket/{ticket}/hardware",
        [App\Http\Controllers\TicketController::class, "hardware"]
    );

    Route::get(
        "/ticket/{ticket}/files",
        [App\Http\Controllers\TicketController::class, "files"]
    );

    // Questa route non viene usata al momento. se serve, provare prima l'altra (storeFiles)
    Route::post(
        "/ticket/{ticket}/file",
        [App\Http\Controllers\TicketController::class, "storeFile"]
    );

    Route::post(
        "/ticket/{ticket}/files",
        [App\Http\Controllers\TicketController::class, "storeFiles"]
    );

    Route::post(
        "/ticket/{ticket}/priority-update",
        [App\Http\Controllers\TicketController::class, "updateTicketPriority"]
    );

    Route::get(
        "/ticket/{ticket}/blame",
        [App\Http\Controllers\TicketController::class, "getTicketBlame"]
    );
    Route::post(
        "/ticket/{ticket}/blame",
        [App\Http\Controllers\TicketController::class, "updateTicketBlame"]
    );

    Route::post(
        "/ticket/{ticket}/blame-update",
        [App\Http\Controllers\TicketController::class, "updateTicketBlame"]
    );

    Route::post(
        "/ticket/{ticket}/status-update",
        [App\Http\Controllers\TicketController::class, "updateStatus"]
    );

    Route::post(
        "/ticket/{ticket}/add-note",
        [App\Http\Controllers\TicketController::class, "addNote"]
    );

    Route::post(
        "/ticket/{ticket}/close",
        [App\Http\Controllers\TicketController::class, "closeTicket"]
    );

    Route::get(
        "/ticket/file/{id}/temporary_url",
        [App\Http\Controllers\TicketController::class, "generatedSignedUrlForFile"]
    );
    Route::post(
        "/ticket/file/{id}/delete",
        [App\Http\Controllers\TicketController::class, "deleteFile"]
    );
    Route::post(
        "/ticket/file/{id}/recover",
        [App\Http\Controllers\TicketController::class, "recoverFile"]
    );

    Route::get(
        "/ticket-admin",
        [App\Http\Controllers\TicketController::class, "adminGroupsTickets"]
    );

    Route::post(
        "/ticket/{ticket}/assign-to-admin",
        [App\Http\Controllers\TicketController::class, "assignToAdminUser"]
    );
    Route::post(
        "/ticket/{ticket}/assign-to-group",
        [App\Http\Controllers\TicketController::class, "assignToGroup"]
    );
    Route::get(
        "/ticket/{ticket}/closing-messages",
        [App\Http\Controllers\TicketController::class, "closingMessages"]
    );

    Route::get(
        "/ticket/{ticket}/report",
        [App\Http\Controllers\TicketController::class, "report"]
    );

    Route::get(
        "/ticket-report/batch",
        [App\Http\Controllers\TicketController::class, "batchReport"]
    );
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('attendance', App\Http\Controllers\AttendanceController::class);
    Route::get('presenze-type', [App\Http\Controllers\AttendanceController::class, "types"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, "storeBatch"]);
    Route::patch('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, "updateBatch"]);

    Route::resource('time-off-request', App\Http\Controllers\TimeOffRequestController::class);
    Route::get('time-off-type', [App\Http\Controllers\TimeOffRequestController::class, "types"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('business-trip', App\Http\Controllers\BusinessTripController::class);
    Route::get('business-trip/{business_trip}/expense', [App\Http\Controllers\BusinessTripController::class, "getExpenses"]);
    Route::post('business-trip/{business_trip}/expense', [App\Http\Controllers\BusinessTripController::class, "storeExpense"]);
    Route::get('business-trip/{business_trip}/transfer', [App\Http\Controllers\BusinessTripController::class, "getTransfers"]);
    Route::post('business-trip/{business_trip}/transfer', [App\Http\Controllers\BusinessTripController::class, "storeTransfer"]);
});

Route::middleware(['auth:sanctum'])->get(
    "/ticket-types",
    [App\Http\Controllers\UserController::class, "ticketTypes"]
);

Route::middleware(['auth:sanctum'])->group(function () {


    Route::get(
        "/ticket-type/all",
        [App\Http\Controllers\TicketTypeController::class, "index"]
    );

    Route::get(
        "/ticket-type/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "show"]
    );

    // Eliminazione di tipo e categoria da rivedere
    Route::delete(
        "/ticket-type/{ticketType}/delete",
        [App\Http\Controllers\TicketTypeController::class, "destroy"]
    );
    // TicketTypeCategory spostarli tutti in un gruppo loro e nel controller giusto.
    Route::delete(
        "/ticket-type-category/{ticketTypeCategory}/delete",
        [App\Http\Controllers\TicketTypeCategoryController::class, "destroy"]
    );

    Route::get(
        "/ticket-type-categories",
        [App\Http\Controllers\TicketTypeController::class, "categories"]
    );

    Route::patch(
        "/ticket-type-category/{ticketTypeCategory}",
        [App\Http\Controllers\TicketTypeController::class, "updateCategory"]
    );

    Route::get(
        "/ticket-type-groups/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "getGroups"]
    );

    Route::get(
        "/ticket-type-webform/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "getWebForm"]
    );

    // Route::get(
    //     "/ticket-type-companies/{ticketType}",
    //     [App\Http\Controllers\TicketTypeController::class, "getCompanies"]
    // );

    Route::get(
        "/ticket-type-company/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "getCompany"]
    );

    // Route::patch(
    //     "/ticket-type/update-sla",
    //     [App\Http\Controllers\TicketTypeController::class, "updateSla"]
    // );

    // Restituisce il numero di ticket del tipo indicato per l'azienda assegnata al tipo
    Route::get(
        "/ticket-type/{ticketTypeId}/count-company",
        [App\Http\Controllers\TicketTypeController::class, "countTicketsInCompany"]
    );

    Route::patch(
        "/ticket-type/{ticketType}",
        [App\Http\Controllers\TicketTypeController::class, "update"]
    );

    Route::post(
        "/ticket-type",
        [App\Http\Controllers\TicketTypeController::class, "store"]
    );

    Route::post(
        "/ticket-type-category",
        [App\Http\Controllers\TicketTypeController::class, "storeCategory"]
    );

    Route::post(
        "/ticket-type-webform",
        [App\Http\Controllers\TicketTypeController::class, "createFormField"]
    );

    Route::post(
        "/ticket-type-webform/{formFieldId}/delete",
        [App\Http\Controllers\TicketTypeController::class, "deleteFormField"]
    );

    Route::post(
        "/ticket-type-groups",
        [App\Http\Controllers\TicketTypeController::class, "updateGroups"]
    );

    Route::post(
        "/ticket-type-groups/delete",
        [App\Http\Controllers\TicketTypeController::class, "deleteGroups"]
    );

    Route::post(
        "/ticket-type-companies",
        [App\Http\Controllers\TicketTypeController::class, "updateCompanies"]
    );

    // Route::post(
    //     "/ticket-type-companies/delete",
    //     [App\Http\Controllers\TicketTypeController::class, "deleteCompany"]
    // );

    Route::post(
        "/ticket-type/duplicate",
        [App\Http\Controllers\TicketTypeController::class, "duplicateTicketType"]
    );
});

// Route::middleware(['auth:sanctum'])->get(
//     "/ticket-type-webform/{ticketType}", 
//     [App\Http\Controllers\TicketTypeController::class, "getWebForm"]
// );

Route::post(
    "/upload-file",
    [App\Http\Controllers\FileUploadController::class, "uploadFileToCloud"]
);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get(
        "/groups",
        [GroupController::class, "index"]
    );

    Route::get(
        "/groups/{group}",
        [GroupController::class, "show"]
    );

    Route::patch(
        "/groups/{group}",
        [GroupController::class, "update"]
    );

    Route::get(
        "/groups/{group}/ticket-types",
        [GroupController::class, "ticketTypes"]
    );

    Route::get(
        "/groups/{group}/users",
        [GroupController::class, "users"]
    );

    Route::post(
        "/groups",
        [GroupController::class, "store"]
    );

    Route::post(
        "/groups-users",
        [GroupController::class, "updateUsers"]
    );
    Route::post(
        "/groups-types",
        [GroupController::class, "updateTypes"]
    );
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get("/brands", [BrandController::class, "index"]);
    Route::get("/brands/{brand}", [BrandController::class, "show"]);
    Route::post("/brands", [BrandController::class, "store"]);
    Route::patch("/brands/{brand}", [BrandController::class, "update"]);
    Route::delete("/brands/{brand}", [BrandController::class, "destroy"]);
    Route::post("/brands/{brand}/logo", [BrandController::class, "uploadLogo"]);
});
// Route usata per i loghi nelle mail. non deve richiedere l'autenticazione.
Route::get("/brand/{brand}/logo", [BrandController::class, "getLogo"]);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get("/suppliers", [App\Http\Controllers\SupplierController::class, "index"]);
    Route::get("/suppliers/{supplier}", [App\Http\Controllers\SupplierController::class, "show"]);
    Route::post("/suppliers", [App\Http\Controllers\SupplierController::class, "store"]);
    Route::patch("/suppliers/{supplier}", [App\Http\Controllers\SupplierController::class, "update"]);
    Route::delete("/suppliers/{supplier}", [App\Http\Controllers\SupplierController::class, "destroy"]);
    Route::post("/suppliers/{supplier}/logo", [App\Http\Controllers\SupplierController::class, "uploadLogo"]);
    Route::get("/suppliers/{supplier}/brands", [App\Http\Controllers\SupplierController::class, "brands"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get("/stats", [App\Http\Controllers\TicketStatsController::class, "latestStats"]);
});


Route::middleware(['auth:sanctum'])->group(function () {
    // batch deve stare sopra altrimenti la route non viene trovata (viene interpretata come un id {ticket})
    Route::get("/ticket-report/pdf/batch", [App\Http\Controllers\TicketReportExportController::class, "exportBatch"]);
    Route::get("/ticket-report/pdf/{ticket}", [App\Http\Controllers\TicketReportExportController::class, "exportpdf"]);
});

/** 
 * Esportazioni
 */
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get("/ticket-report/list/{company}", [App\Http\Controllers\TicketReportExportController::class, "company"]);
    Route::post("/ticket-report/export", [App\Http\Controllers\TicketReportExportController::class, "export"]);
    Route::get("/ticket-report/export/{ticketReportExport}", [App\Http\Controllers\TicketReportExportController::class, "show"]);
    Route::delete("/ticket-report/export/{ticketReportExport}", [App\Http\Controllers\TicketReportExportController::class, "destroy"]);
    Route::get("/ticket-report/download/{ticketReportExport}", [App\Http\Controllers\TicketReportExportController::class, "download"]);

    Route::get("/generic-ticket-report/list", [App\Http\Controllers\TicketReportExportController::class, "generic"]);
    Route::post("/generic-ticket-report/export", [App\Http\Controllers\TicketReportExportController::class, "genericExport"]);

    Route::get("/user-ticket-report/list", [App\Http\Controllers\TicketReportExportController::class, "user"]);
    Route::post("/user-ticket-report/export", [App\Http\Controllers\TicketReportExportController::class, "userExport"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get("/ticket-report/user-stats", [App\Http\Controllers\TicketStatsController::class, "statsForCompany"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get("/hardware-types", [App\Http\Controllers\HardwareTypeController::class, "index"]);
    Route::post("/hardware-types", [App\Http\Controllers\HardwareTypeController::class, "store"]);
    Route::patch("/hardware-types/{hardwareType}", [App\Http\Controllers\HardwareTypeController::class, "update"]);
    Route::delete("/hardware-types/{hardwareType}", [App\Http\Controllers\HardwareTypeController::class, "destroy"]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get("/hardware-list", [App\Http\Controllers\HardwareController::class, "index"]);
    Route::get("/company-hardware-list/{company}", [App\Http\Controllers\HardwareController::class, "companyHardwareList"]);
    Route::get("/form-field-hardware-list/{typeFormField}", [App\Http\Controllers\HardwareController::class, "formFieldHardwareList"]);
    Route::get("/hardware-list-full", [App\Http\Controllers\HardwareController::class, "hardwareListWithTrashed"]);
    Route::post("/hardware", [App\Http\Controllers\HardwareController::class, "store"]);
    Route::delete("/hardware/{hardware}", [App\Http\Controllers\HardwareController::class, "destroy"]);
    Route::post("/hardware-restore/{hardware}", [App\Http\Controllers\HardwareController::class, "restore"]);
    Route::delete("/hardware-trashed/{hardware}", [App\Http\Controllers\HardwareController::class, "destroyTrashed"]);
    Route::patch("/hardware/{hardware}", [App\Http\Controllers\HardwareController::class, "update"]);
    Route::patch("/hardware-users/{hardware}", [App\Http\Controllers\HardwareController::class, "updateHardwareUsers"]); //lato utente
    Route::delete("/hardware-user/{hardware}/{user}", [App\Http\Controllers\HardwareController::class, "deleteHardwareUser"]);
    Route::get("/user-hardware/{user}", [App\Http\Controllers\HardwareController::class, "userHardwareList"]); //lato utente
    Route::get("/hardware/{hardware}", [App\Http\Controllers\HardwareController::class, "show"]);
    Route::get("/fake-hardware-field", [App\Http\Controllers\HardwareController::class, "fakeHardwareField"]);
    Route::get("/hardware-tickets/{hardware}", [App\Http\Controllers\HardwareController::class, "hardwareTickets"]);
});

/** Help (guide) */

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/files/public/search', [App\Http\Controllers\WikiObjectController::class, "searchPublic"]);
    Route::get("/files/public/{folder}", [App\Http\Controllers\WikiObjectController::class, "public"]);

    Route::get('/wiki-files/{wikiObject}', [App\Http\Controllers\WikiObjectController::class, "downloadFile"]);
    Route::get('/files/search', [App\Http\Controllers\WikiObjectController::class, "search"]);
    Route::get("/files/{folder}", [App\Http\Controllers\WikiObjectController::class, "index"]);
    Route::post("/files", [App\Http\Controllers\WikiObjectController::class, "store"]);
});

// Sembra inesistente. commento per sicurezza e poi eliminiamo in un commit futuro
// Route::middleware(['auth:sanctum'])->group(function () {
//     Route::get("/template-export/{type}", [App\Http\Controllers\TemplatesExportController::class, "index"]);
// });