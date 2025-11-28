<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TicketCauseController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\TicketStatusUpdateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Tenant Terms Routes (Public) - Moved outside auth middleware
Route::get('/tenant-terms/public', [App\Http\Controllers\TenantTermController::class, 'index']);
Route::get('/tenant-terms/public/{key}', [App\Http\Controllers\TenantTermController::class, 'show']);
Route::get('/tenant-terms/public/category/{category}', [App\Http\Controllers\TenantTermController::class, 'byCategory']);

// PUBLIC ROUTES

// Brand Routes
Route::get('/brand/{brand}/logo', [BrandController::class, 'getLogo']);
Route::get('/supplier/{supplier}/logo', [SupplierController::class, 'getLogo']);
Route::get('/brands/logos', [BrandController::class, 'getLogos']);

// Company Document Routes (Public with rate limiting)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/companies-documents/{company}/privacy-policy', [CompanyController::class, 'downloadPrivacyPolicy']);
    Route::get('/companies-documents/{company}/cookie-policy', [CompanyController::class, 'downloadCookiePolicy']);
});

// File Upload Routes
Route::post('/upload-file', [App\Http\Controllers\FileUploadController::class, 'uploadFileToCloud']);

// Feature Flags Routes
Route::get('/features', [App\Http\Controllers\FeatureFlagController::class, 'getFeatures']);
Route::post('/features/flush', [App\Http\Controllers\FeatureFlagController::class, 'flushFeatureFlags']);

// AUTHENTICATION ROUTES

Route::middleware(['auth:sanctum', 'admin.or.company'])->group(function () {

    // User Routes

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->company_id = $user->selectedCompany() ? $user->selectedCompany()->id : null;

        return $user;
    });
    Route::post('/user/{id}/companies', [App\Http\Controllers\UserController::class, 'addCompaniesForUser']);
    Route::post('/onboarding', [App\Http\Controllers\UserController::class, 'onboarding']);
    Route::patch('/user/profile', [App\Http\Controllers\UserController::class, 'updateProfile']);
    Route::patch('/user/password', [App\Http\Controllers\UserController::class, 'passwordUpdate']);
    Route::get('/user/all', [App\Http\Controllers\UserController::class, 'allUsers']);
    Route::post('/user', [App\Http\Controllers\UserController::class, 'store']);
    Route::patch('/user', [App\Http\Controllers\UserController::class, 'update']);
    Route::delete('/user/{id}', [App\Http\Controllers\UserController::class, 'destroy']);
    Route::get('/user/{id}/enable', [App\Http\Controllers\UserController::class, 'enable']);
    Route::get('/user/alladmins-ids', [App\Http\Controllers\UserController::class, 'adminsIds']);
    Route::get('/user/alladmins', [App\Http\Controllers\UserController::class, 'allAdmins']);
    Route::get('/user/{id}/get-name', [App\Http\Controllers\UserController::class, 'getName']);
    Route::get('/user/frontend/logo', [App\Http\Controllers\UserController::class, 'getFrontendLogoUrl']);
    Route::get('/user/export-template', [App\Http\Controllers\UserController::class, 'exportTemplate']);
    Route::post('/user/import', [App\Http\Controllers\UserController::class, 'importUsers']);
    Route::get('/user-test', [App\Http\Controllers\UserController::class, 'ticketTypes']);
    Route::get('/user-tickets/{id}', [App\Http\Controllers\UserController::class, 'userTickets']);
    Route::get('/user/{id}/companies', [App\Http\Controllers\UserController::class, 'companiesForUser']);
    Route::delete('/user/{id}/companies/{company}', [App\Http\Controllers\UserController::class, 'deleteCompaniesForUser']);
    Route::get('/user/{id}', [App\Http\Controllers\UserController::class, 'show']);
    Route::get('/user-logs/{user}/export', [App\Http\Controllers\UserController::class, 'userLogsExport']);
    Route::get('/user-logs/{user}', [App\Http\Controllers\UserController::class, 'getUserLog']);

    // Ticket Routes

    Route::post('/ticket/{ticket_id}/message', [TicketMessageController::class, 'store']);
    Route::get('/ticket/{ticket_id}/messages', [TicketMessageController::class, 'index']);
    Route::get('/ticket/{ticket_id}/status-updates', [TicketStatusUpdateController::class, 'index']);

    // Ticket Causes Routes
    Route::get('/ticket-causes', [TicketCauseController::class, 'index']);
    Route::get('/all-ticket-causes', [TicketCauseController::class, 'all']);
    Route::post('/ticket-causes', [TicketCauseController::class, 'store']);
    Route::get('/ticket-causes/{ticketCause}', [TicketCauseController::class, 'show']);
    Route::put('/ticket-causes/{ticketCause}', [TicketCauseController::class, 'update']);
    Route::delete('/ticket-causes/{ticketCause}', [TicketCauseController::class, 'destroy']);
    Route::delete('/ticket-causes/{id}/force', [TicketCauseController::class, 'forceDestroy']);
    Route::post('/ticket-causes/{id}/restore', [TicketCauseController::class, 'restore']);



    Route::resource('ticket', App\Http\Controllers\TicketController::class);
    Route::get('/old-ticket-search', [App\Http\Controllers\OldTicketController::class, 'search']);
    Route::get('/ticket-search', [App\Http\Controllers\TicketController::class, 'search']);
    Route::get('/data-owner/ticket/{ticket}', [App\Http\Controllers\TicketController::class, 'show']);
    Route::post('/ticketmassive', [App\Http\Controllers\TicketController::class, 'storeMassive']);
    Route::get('/ticket/{ticket}/hardware', [App\Http\Controllers\TicketController::class, 'hardware']);
    Route::get('/ticket/{ticket}/files', [App\Http\Controllers\TicketController::class, 'files']);
    Route::post('/ticket/{ticket}/file', [App\Http\Controllers\TicketController::class, 'storeFile']);
    Route::post('/ticket/{ticket}/files', [App\Http\Controllers\TicketController::class, 'storeFiles']);
    Route::post('/ticket/{ticket}/priority-update', [App\Http\Controllers\TicketController::class, 'updateTicketPriority']);
    Route::post('/ticket/{ticket}/billable-update', [App\Http\Controllers\TicketController::class, 'updateTicketIsBillable']);
    Route::post('/ticket/{ticket}/billed-update', [App\Http\Controllers\TicketController::class, 'updateTicketIsBilled']);
    Route::post('/ticket/{ticket}/bill-details-update', [App\Http\Controllers\TicketController::class, 'updateTicketBillDetails']);
    Route::post('/ticket/{ticket}/billing-info-update', [App\Http\Controllers\TicketController::class, 'updateTicketBillingInfo']);
    Route::get('/ticket/{ticket}/blame', [App\Http\Controllers\TicketController::class, 'getTicketBlame']);
    Route::post('/ticket/{ticket}/blame', [App\Http\Controllers\TicketController::class, 'updateTicketBlame']);
    Route::post('/ticket/{ticket}/blame-update', [App\Http\Controllers\TicketController::class, 'updateTicketBlame']);
    Route::post('/ticket/{ticket}/status-update', [App\Http\Controllers\TicketController::class, 'updateStatus']);
    Route::post('/ticket/{ticket}/time-update', [App\Http\Controllers\TicketController::class, 'updateTicketActualProcessingTime']);
    Route::post('/ticket/{ticket}/work-mode-update', [App\Http\Controllers\TicketController::class, 'updateTicketWorkMode']);
    Route::post('/ticket/{ticket}/add-note', [App\Http\Controllers\TicketController::class, 'addNote']);
    Route::post('/ticket/{ticket}/close', [App\Http\Controllers\TicketController::class, 'closeTicket']);
    Route::get('/ticket/file/{id}/temporary_url', [App\Http\Controllers\TicketController::class, 'generatedSignedUrlForFile']);
    Route::post('/ticket/file/{id}/delete', [App\Http\Controllers\TicketController::class, 'deleteFile']);
    Route::post('/ticket/file/{id}/recover', [App\Http\Controllers\TicketController::class, 'recoverFile']);
    Route::get('/ticket-admin', [App\Http\Controllers\TicketController::class, 'adminGroupsTickets']);
    Route::get('/ticket-admin-billing', [App\Http\Controllers\TicketController::class, 'adminGroupsBillingTickets']);
    Route::get('/ticket-admin-billing-counters', [App\Http\Controllers\TicketController::class, 'adminGroupsBillingCounters']);
    Route::post('/ticket/{ticket}/assign-to-admin', [App\Http\Controllers\TicketController::class, 'assignToAdminUser']);
    Route::post('/ticket/{ticket}/assign-to-group', [App\Http\Controllers\TicketController::class, 'assignToGroup']);
    Route::get('/ticket/{ticket}/closing-messages', [App\Http\Controllers\TicketController::class, 'closingMessages']);
    Route::get('/ticket/{ticket}/report', [App\Http\Controllers\TicketController::class, 'report']);
    Route::get('/ticket-report/batch', [App\Http\Controllers\TicketController::class, 'batchReport']);
    Route::get('/ticket-types', [App\Http\Controllers\UserController::class, 'ticketTypes']);
    Route::get('/ticket/{ticket}/slave-tickets', [App\Http\Controllers\TicketController::class, 'getSlaveTickets']);
    // Scheduling Tickets Routes
    Route::get('/ticket/{ticket}/available-scheduling-tickets', [App\Http\Controllers\TicketController::class, 'getAvailableSchedulingTickets']);
    Route::get('/ticket/{ticket}/connected-to-scheduling', [App\Http\Controllers\TicketController::class, 'getTicketsConnectedToScheduling']);
    Route::post('/ticket/{ticket}/connect-to-scheduling', [App\Http\Controllers\TicketController::class, 'connectToSchedulingTicket']);
    Route::post('/ticket/{ticket}/remove-scheduling-connection', [App\Http\Controllers\TicketController::class, 'removeSchedulingConnection']);
    Route::get('/ticket/{ticket}/scheduling-ticket-recap-data', [App\Http\Controllers\TicketController::class, 'getSchedulingTicketRecapData']);
    // ProjectTickets Routes
    Route::get('/ticket/{ticket}/available-project-tickets', [App\Http\Controllers\TicketController::class, 'getAvailableProjectTickets']);
    Route::get('/ticket/{ticket}/connected-to-project', [App\Http\Controllers\TicketController::class, 'getTicketsConnectedToProject']);
    Route::post('/ticket/{ticket}/connect-to-project', [App\Http\Controllers\TicketController::class, 'connectToProjectTicket']);
    Route::post('/ticket/{ticket}/remove-project-connection', [App\Http\Controllers\TicketController::class, 'removeProjectConnection']);
    Route::get('/ticket/{ticket}/project-ticket-recap-data', [App\Http\Controllers\TicketController::class, 'getProjectTicketRecapData']);
    Route::post('/ticket/{ticket}/project-update', [App\Http\Controllers\TicketController::class, 'updateProject']);

    // Assegnazione ticket

    Route::get('/ticket-assign/{ticket}/history', [App\Http\Controllers\TicketController::class, 'asignmentHistory']);
    Route::get('/ticket-assign/{ticket}/handler', [App\Http\Controllers\TicketController::class, 'getCurrentHandler']);
    Route::get('/ticket-assign/groups', [App\Http\Controllers\GroupController::class, 'groupsWithUsers']);
    Route::post('/ticket-assign/assign', [App\Http\Controllers\TicketController::class, 'assign']);
    Route::post('/ticket-assign/reassign', [App\Http\Controllers\TicketController::class, 'reassign']);

    // Ticket Log Routes

    Route::get('/ticket/{ticket}/logs', [App\Http\Controllers\TicketLogController::class, 'index']);
    Route::get('/ticket-logs/{ticketLog}', [App\Http\Controllers\TicketLogController::class, 'show']);

    // Company Routes

    Route::get('/companies/allowed', [App\Http\Controllers\UserController::class, 'companies']);
    Route::post('/companies/set-active', [App\Http\Controllers\UserController::class, 'setActiveCompany']);
    Route::post('/companies/reset-active', [App\Http\Controllers\UserController::class, 'resetActiveCompany']);
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{id}', [CompanyController::class, 'show']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);
    Route::patch('/companies', [CompanyController::class, 'update']);
    Route::get('/companies/{company}/offices', [CompanyController::class, 'offices']);
    Route::get('/companies/{company}/admins', [CompanyController::class, 'admins']);
    Route::get('/companies/{company}/allusers', [CompanyController::class, 'allusers']);
    Route::post('/companies/{host}/ticket-types/import-from/{guest}', [CompanyController::class, 'ticketTypesDuplicate']);
    Route::get('/companies/{company}/ticket-types/export', [CompanyController::class, 'ticketTypesExport']);
    Route::get('/companies/{company}/ticket-types', [CompanyController::class, 'ticketTypes']);
    Route::get('/companies/{company}/brands', [CompanyController::class, 'brands']);
    Route::get('/companies/{company}/frontend-logo', [CompanyController::class, 'getFrontendLogoUrl']);
    Route::get('/companies/{company}/tickets', [CompanyController::class, 'tickets']);
    Route::get('/companies/{company}/projects', [CompanyController::class, 'projects']);
    Route::get('/companies/{company}/weekly-times', [CompanyController::class, 'getWeeklyTimes']);
    Route::get('/companies/{company}/master-tickets', [CompanyController::class, 'getMasterTickets']);
    Route::post('/companies/{company}/weekly-times', [CompanyController::class, 'editWeeklyTime']);
    Route::post('/companies/{company}/logo', [CompanyController::class, 'uploadLogo']);
    Route::post('/companies/{company}/update-reading-delay-warning', [CompanyController::class, 'updateDelayWarning']);

    Route::get('/companies/{company}/news-sources', [CompanyController::class, 'getNewsSources']);
    Route::get('/companies/{company}/available-news-sources', [CompanyController::class, 'availableNewsSources']);
    Route::post('/companies/news-sources', [CompanyController::class, 'addNewsSources']);
    Route::delete('/companies/{company}/news-sources/{news_source}', [CompanyController::class, 'removeNewsSources']);

    // Company Documents Routes
    Route::post('/companies/privacy-policy', [CompanyController::class, 'uploadPrivacyPolicy']);
    Route::post('/companies/cookie-policy', [CompanyController::class, 'uploadCookiePolicy']);
    Route::get('/companies/{company}/privacy-policy', [CompanyController::class, 'downloadPrivacyPolicy']);
    Route::get('/companies/{company}/cookie-policy', [CompanyController::class, 'downloadCookiePolicy']);

    Route::get('/companies-dashboard', [CompanyController::class, 'dashboardCompanies']);
    Route::get('/companies-dashboard/{company}/open-master-tickets', [CompanyController::class, 'openMasterTickets']);
    Route::get('/companies-dashboard/{company}/open-activity-tickets', [CompanyController::class, 'openActivityTickets']);

    // Gruppi custom

    Route::get('/companies/{company}/custom-groups', [CompanyController::class, 'getCustomUserGroups']);
    Route::post('/companies/custom-groups', [CompanyController::class, 'storeCustomUserGroup']);
    Route::get('/custom-groups/{customUserGroup}', [CompanyController::class, 'getCustomUserGroup']);
    Route::get('/custom-groups/{customUserGroup}/users', [CompanyController::class, 'getUsersForGroup']);
    Route::get('/custom-groups/{customUserGroup}/ticket-types', [CompanyController::class, 'getCustomUserGroupTicketTypes']);
    Route::get('/custom-groups/{customUserGroup}/available-users', [CompanyController::class, 'getAvailableUsers']);
    Route::get('/custom-groups/{customUserGroup}/available-ticket-types', [CompanyController::class, 'getAvailableTicketTypes']);
    Route::post('/custom-groups/users', [CompanyController::class, 'addUsersToGroup']);
    Route::post('/custom-groups/ticket-types', [CompanyController::class, 'addTicketTypesToGroup']);
    Route::delete('/custom-groups/users', [CompanyController::class, 'removeUsersFromGroup']);
    Route::delete('/custom-groups/ticket-types', [CompanyController::class, 'removeTicketTypesFromGroup']);
    Route::post('/custom-groups/{customUserGroup}', [CompanyController::class, 'updateCustomUserGroup']);

    // Office Routes

    Route::resource('offices', App\Http\Controllers\OfficeController::class);

    // Attendance Routes

    Route::resource('attendance', App\Http\Controllers\AttendanceController::class);
    Route::get('presenze-type', [App\Http\Controllers\AttendanceController::class, 'types']);

    // Time Off Request Routes

    Route::post('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, 'storeBatch']);
    Route::patch('time-off-request/batch', [App\Http\Controllers\TimeOffRequestController::class, 'updateBatch']);
    Route::resource('time-off-request', App\Http\Controllers\TimeOffRequestController::class);
    Route::get('time-off-type', [App\Http\Controllers\TimeOffRequestController::class, 'types']);

    // Business Trip Routes

    Route::resource('business-trip', App\Http\Controllers\BusinessTripController::class);
    Route::get('business-trip/{business_trip}/expense', [App\Http\Controllers\BusinessTripController::class, 'getExpenses']);
    Route::post('business-trip/{business_trip}/expense', [App\Http\Controllers\BusinessTripController::class, 'storeExpense']);
    Route::get('business-trip/{business_trip}/transfer', [App\Http\Controllers\BusinessTripController::class, 'getTransfers']);
    Route::post('business-trip/{business_trip}/transfer', [App\Http\Controllers\BusinessTripController::class, 'storeTransfer']);

    // Brand Routes

    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{brand}', [BrandController::class, 'show']);
    Route::post('/brands', [BrandController::class, 'store']);
    Route::patch('/brands/{brand}', [BrandController::class, 'update']);
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy']);
    Route::post('/brands/{brand}/logo', [BrandController::class, 'uploadLogo']);

    // Supplier Routes

    Route::get('/suppliers', [App\Http\Controllers\SupplierController::class, 'index']);
    Route::get('/suppliers/{supplier}', [App\Http\Controllers\SupplierController::class, 'show']);
    Route::post('/suppliers', [App\Http\Controllers\SupplierController::class, 'store']);
    Route::patch('/suppliers/{supplier}', [App\Http\Controllers\SupplierController::class, 'update']);
    Route::delete('/suppliers/{supplier}', [App\Http\Controllers\SupplierController::class, 'destroy']);
    Route::post('/suppliers/{supplier}/logo', [App\Http\Controllers\SupplierController::class, 'uploadLogo']);
    Route::get('/suppliers/{supplier}/brands', [App\Http\Controllers\SupplierController::class, 'brands']);

    // Group Routes

    Route::get('/groups', [GroupController::class, 'index']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);
    Route::patch('/groups/{group}', [GroupController::class, 'update']);
    Route::get('/groups/{group}/ticket-types', [GroupController::class, 'ticketTypes']);
    Route::get('/groups/{group}/users', [GroupController::class, 'users']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::post('/groups-users', [GroupController::class, 'updateUsers']);
    Route::post('/groups-types', [GroupController::class, 'updateTypes']);

    // Ticket Type Routes

    Route::get('/ticket-type/all', [App\Http\Controllers\TicketTypeController::class, 'index']);
    Route::get('/ticket-type/{ticketType}/context', [App\Http\Controllers\TicketTypeController::class, 'context']);
    Route::get('/ticket-type/{ticketType}', [App\Http\Controllers\TicketTypeController::class, 'show']);
    Route::delete('/ticket-type/{ticketType}/delete', [App\Http\Controllers\TicketTypeController::class, 'destroy']);
    Route::delete('/ticket-type-category/{ticketTypeCategory}/delete', [App\Http\Controllers\TicketTypeCategoryController::class, 'destroy']);
    Route::get('/ticket-type-categories', [App\Http\Controllers\TicketTypeController::class, 'categories']);
    Route::patch('/ticket-type-category/{ticketTypeCategory}', [App\Http\Controllers\TicketTypeController::class, 'updateCategory']);
    Route::get('/ticket-type-groups/{ticketType}', [App\Http\Controllers\TicketTypeController::class, 'getGroups']);
    Route::get('/ticket-type-webform/{ticketType}', [App\Http\Controllers\TicketTypeController::class, 'getWebForm']);
    Route::get('/ticket-type-company/{ticketType}', [App\Http\Controllers\TicketTypeController::class, 'getCompany']);
    Route::get('/ticket-type/{ticketTypeId}/count-company', [App\Http\Controllers\TicketTypeController::class, 'countTicketsInCompany']);
    Route::patch('/ticket-type/{ticketType}', [App\Http\Controllers\TicketTypeController::class, 'update']);
    Route::post('/ticket-type', [App\Http\Controllers\TicketTypeController::class, 'store']);
    Route::post('/ticket-type-category', [App\Http\Controllers\TicketTypeController::class, 'storeCategory']);
    Route::post('/ticket-type-webform', [App\Http\Controllers\TicketTypeController::class, 'createFormField']);
    Route::post('/ticket-type-webform/{formFieldId}/delete', [App\Http\Controllers\TicketTypeController::class, 'deleteFormField']);
    Route::post('/ticket-type-groups', [App\Http\Controllers\TicketTypeController::class, 'updateGroups']);
    Route::post('/ticket-type-groups/delete', [App\Http\Controllers\TicketTypeController::class, 'deleteGroups']);
    Route::post('/ticket-type-companies', [App\Http\Controllers\TicketTypeController::class, 'updateCompanies']);
    Route::post('/ticket-type/duplicate', [App\Http\Controllers\TicketTypeController::class, 'duplicateTicketType']);
    Route::post('/ticket-type/{ticketType}/hourly-cost', [App\Http\Controllers\TicketTypeController::class, 'updateHourlyCost']);

    // Custom groups

    Route::get('/ticket-type/{ticketType}/custom-groups', [App\Http\Controllers\TicketTypeController::class, 'getCustomGroups']);
    Route::post('/ticket-type/custom-groups', [App\Http\Controllers\TicketTypeController::class, 'addCustomGroup']);
    Route::delete('/ticket-type/custom-groups', [App\Http\Controllers\TicketTypeController::class, 'removeCustomGroup']);
    Route::get('/ticket-type/{ticketType}/available-custom-groups', [App\Http\Controllers\TicketTypeController::class, 'getAvailableCustomGroups']);
    Route::post('/ticket-type/{ticketType}/custom-group-exclusive', [App\Http\Controllers\TicketTypeController::class, 'setCustomGroupExclusive']);

    // Slaves

    Route::get('/ticket-type/{ticketType}/slaves', [App\Http\Controllers\TicketTypeController::class, 'getSlaveTypes']);
    Route::post('/ticket-type/{ticketType}/slaves', [App\Http\Controllers\TicketTypeController::class, 'editSlaveTypes']);
    Route::get('/ticket-type/{ticketType}/available-slaves', [App\Http\Controllers\TicketTypeController::class, 'getAvailableSlaveTypes']);

    // Ticket Report Routes

    Route::get('/ticket-report/pdf/batch', [App\Http\Controllers\TicketReportExportController::class, 'exportBatch']);
    Route::get('/ticket-report/pdf/{ticket}', [App\Http\Controllers\TicketReportExportController::class, 'exportpdf']);
    Route::get('/ticket-report/list/{company}', [App\Http\Controllers\TicketReportExportController::class, 'company']);
    Route::post('/ticket-report/export', [App\Http\Controllers\TicketReportExportController::class, 'export']);
    Route::get('/ticket-report/export/{ticketReportExport}', [App\Http\Controllers\TicketReportExportController::class, 'show']);
    Route::delete('/ticket-report/export/{ticketReportExport}', [App\Http\Controllers\TicketReportExportController::class, 'destroy']);
    Route::get('/ticket-report/download/{ticketReportExport}', [App\Http\Controllers\TicketReportExportController::class, 'download']);
    Route::get('/generic-ticket-report/list', [App\Http\Controllers\TicketReportExportController::class, 'generic']);
    Route::post('/generic-ticket-report/export', [App\Http\Controllers\TicketReportExportController::class, 'genericExport']);
    Route::get('/user-ticket-report/list', [App\Http\Controllers\TicketReportExportController::class, 'user']);
    Route::post('/user-ticket-report/export', [App\Http\Controllers\TicketReportExportController::class, 'userExport']);
    Route::get('/ticket-report/user-stats', [App\Http\Controllers\TicketStatsController::class, 'statsForCompany']);

    // Ticket PDF Report Routes

    Route::post('/ticket-pdf-report/update', [App\Http\Controllers\TicketReportPdfExportController::class, 'update']);
    Route::post('/ticket-pdf-report/regenerate/', [App\Http\Controllers\TicketReportPdfExportController::class, 'regenerate']);
    Route::delete('/ticket-pdf-report/delete/{ticketReportPdfExport}', [App\Http\Controllers\TicketReportPdfExportController::class, 'destroy']);
    Route::post('/ticket-pdf-report/export', [App\Http\Controllers\TicketReportPdfExportController::class, 'storePdfExport']);
    Route::get('/ticket-pdf-report/list/{company}', [App\Http\Controllers\TicketReportPdfExportController::class, 'pdfCompany']);
    Route::get('/approved-ticket-pdf-report/list/{company}', [App\Http\Controllers\TicketReportPdfExportController::class, 'approvedPdfCompany']);
    Route::get('/ticket-pdf-report/preview/{ticketReportPdfExport}', [App\Http\Controllers\TicketReportPdfExportController::class, 'pdfPreview']);
    Route::get('/ticket-pdf-report/download/{ticketReportPdfExport}', [App\Http\Controllers\TicketReportPdfExportController::class, 'pdfDownload']);

    // Project PDF Report Routes

    Route::post('/project-pdf-report/update', [App\Http\Controllers\ProjectReportPdfExportController::class, 'update']);
    Route::post('/project-pdf-report/regenerate/', [App\Http\Controllers\ProjectReportPdfExportController::class, 'regenerate']);
    Route::delete('/project-pdf-report/delete/{projectReportPdfExport}', [App\Http\Controllers\ProjectReportPdfExportController::class, 'destroy']);
    Route::post('/project-pdf-report/export', [App\Http\Controllers\ProjectReportPdfExportController::class, 'storeProjectPdfExport']);
    Route::get('/project-pdf-report/list/{project}', [App\Http\Controllers\ProjectReportPdfExportController::class, 'pdfProject']);
    Route::get('/approved-project-pdf-report/list/{project}', [App\Http\Controllers\ProjectReportPdfExportController::class, 'approvedProjectPdf']);
    Route::get('/project-pdf-report/preview/{projectReportPdfExport}', [App\Http\Controllers\ProjectReportPdfExportController::class, 'projectPdfPreview']);
    Route::get('/project-pdf-report/download/{projectReportPdfExport}', [App\Http\Controllers\ProjectReportPdfExportController::class, 'projectPdfDownload']);

    // Ticket reminder Routes

    Route::get('/ticket-reminders/{ticketId}/latest-reminder', [App\Http\Controllers\TicketReminderController::class, 'getLatestByTicket']);
    Route::get('/ticket-reminders/{ticketId}/all-reminders', [App\Http\Controllers\TicketReminderController::class, 'listForTicket']);
    Route::get('/ticket-reminders', [App\Http\Controllers\TicketReminderController::class, 'index']);
    Route::post('/ticket-reminders', [App\Http\Controllers\TicketReminderController::class, 'store']);
    Route::get('/ticket-reminders/show/{reminder}', [App\Http\Controllers\TicketReminderController::class, 'show']);
    Route::patch('/ticket-reminders/{reminder}', [App\Http\Controllers\TicketReminderController::class, 'update']);
    Route::delete('/ticket-reminders/{reminder}', [App\Http\Controllers\TicketReminderController::class, 'destroy']);
    Route::get('/ticket-reminders/ics', [App\Http\Controllers\TicketReminderController::class, 'generateIcs']);
    Route::get('/ticket-reminders/deadlines', [App\Http\Controllers\TicketReminderController::class, 'getDeadlineReminders']);
    Route::get('/ticket-reminders/deadlines-this-month', [App\Http\Controllers\TicketReminderController::class, 'getTicketsWithDeadlinesThisMonth']);
    Route::get('/ticket-reminders/{ticketId}/deadline', [App\Http\Controllers\TicketReminderController::class, 'getDeadlineByTicket']);

    // Hardware Routes

    Route::get('/hardware-types', [App\Http\Controllers\HardwareTypeController::class, 'index']);
    Route::post('/hardware-types', [App\Http\Controllers\HardwareTypeController::class, 'store']);
    Route::patch('/hardware-types/{hardwareType}', [App\Http\Controllers\HardwareTypeController::class, 'update']);
    Route::delete('/hardware-types/{hardwareType}', [App\Http\Controllers\HardwareTypeController::class, 'destroy']);
    Route::get('/hardware-list', [App\Http\Controllers\HardwareController::class, 'index']);
    Route::get('/company-hardware-list/{company}', [App\Http\Controllers\HardwareController::class, 'companyHardwareList']);
    Route::get('/user-companies-hardware-list/{user}', [App\Http\Controllers\HardwareController::class, 'userCompaniesHardwareList']);
    Route::get('/form-field-hardware-list/{typeFormField}', [App\Http\Controllers\HardwareController::class, 'formFieldHardwareList']);
    Route::get('/hardware-list-full', [App\Http\Controllers\HardwareController::class, 'hardwareListWithTrashed']);

    // Hardware Export Routes (hardware/export-all va tenuta prima di hardware/{hardware})
    Route::get('/hardware/export-all', [App\Http\Controllers\HardwareController::class, 'exportAllHardware']);
    Route::get('/hardware/export-company/{company}', [App\Http\Controllers\HardwareController::class, 'exportCompanyHardware']);
    Route::get('/hardware/export-user/{user}', [App\Http\Controllers\HardwareController::class, 'exportUserHardware']);

    // Property Routes
    Route::get('/form-field-property-list/{typeFormField}', [App\Http\Controllers\PropertyController::class, 'formFieldPropertyList']);
    Route::post('/hardware', [App\Http\Controllers\HardwareController::class, 'store']);
    Route::get('/hardware/export-template', [App\Http\Controllers\HardwareController::class, 'exportTemplate']);
    Route::get('/hardware-assign/export-template', [App\Http\Controllers\HardwareController::class, 'exportAssignationTemplate']);
    Route::get('/hardware-delete/export-template', [App\Http\Controllers\HardwareController::class, 'exportDeletionTemplate']);
    Route::delete('/hardware/{hardware}', [App\Http\Controllers\HardwareController::class, 'destroy']);
    Route::post('/hardware-restore/{hardware}', [App\Http\Controllers\HardwareController::class, 'restore']);
    Route::delete('/hardware-trashed/{hardware}', [App\Http\Controllers\HardwareController::class, 'destroyTrashed']);
    Route::patch('/hardware/{hardware}', [App\Http\Controllers\HardwareController::class, 'update']);
    Route::patch('/hardware-users/{hardware}', [App\Http\Controllers\HardwareController::class, 'updateHardwareUsers']); //lato utente
    Route::delete('/hardware-user/{hardware}/{user}', [App\Http\Controllers\HardwareController::class, 'deleteHardwareUser']);
    Route::get('/user-hardware/{user}', [App\Http\Controllers\HardwareController::class, 'userHardwareList']); //lato utente
    Route::patch('/user-hardware/{user}', [App\Http\Controllers\HardwareController::class, 'updateUserHardware']);
    Route::get('/hardware/{hardware}', [App\Http\Controllers\HardwareController::class, 'show']);
    Route::get('/fake-hardware-field', [App\Http\Controllers\HardwareController::class, 'fakeHardwareField']);
    Route::get('/hardware-tickets/{hardware}', [App\Http\Controllers\HardwareController::class, 'hardwareTickets']);
    Route::post('/hardwaremassive', [App\Http\Controllers\HardwareController::class, 'importHardware']);
    Route::post('/hardware-assign-massive', [App\Http\Controllers\HardwareController::class, 'importHardwareAssignations']);
    Route::post('/hardware-delete-massive', [App\Http\Controllers\HardwareController::class, 'importHardwareDeletions']);
    Route::get('hardware-user/{hardware}/{user}/download-assignment-pdf', [App\Http\Controllers\HardwareController::class, 'downloadUserAssignmentPdf']);
    Route::post('/delete-hardware-user', [App\Http\Controllers\HardwareController::class, 'deleteHardwareUser']); //rimuovi un'associazione utente-hardware
    Route::get('/hardware-logs/{hardware}/export', [App\Http\Controllers\HardwareController::class, 'hardwareLogsExport']);
    Route::get('/hardware-logs/{hardware}', [App\Http\Controllers\HardwareController::class, 'getHardwareLog']);

    // Hardware Attachments Routes
    Route::get('/hardware/{hardware}/attachments', [App\Http\Controllers\HardwareController::class, 'getAttachments']);
    Route::post('/hardware/{hardware}/attachments', [App\Http\Controllers\HardwareController::class, 'uploadAttachment']);
    Route::post('/hardware/{hardware}/attachments/multiple', [App\Http\Controllers\HardwareController::class, 'uploadAttachments']);
    Route::patch('/hardware/{hardware}/attachments/{attachment}', [App\Http\Controllers\HardwareController::class, 'updateAttachment']);
    Route::delete('/hardware/{hardware}/attachments/{attachment}', [App\Http\Controllers\HardwareController::class, 'deleteAttachment']);
    Route::post('/hardware/{hardware}/attachments/{attachment}/restore', [App\Http\Controllers\HardwareController::class, 'restoreAttachment']);
    Route::delete('/hardware/{hardware}/attachments/{attachment}/force', [App\Http\Controllers\HardwareController::class, 'forceDeleteAttachment']);
    Route::get('/hardware/{hardware}/attachments/{attachment}/download', [App\Http\Controllers\HardwareController::class, 'getDownloadUrl']);
    Route::get('/hardware/{hardware}/attachments/{attachment}/preview', [App\Http\Controllers\HardwareController::class, 'getPreviewUrl']);

    // Software Routes

    Route::get('/software-types', [App\Http\Controllers\SoftwareTypeController::class, 'index']);
    Route::post('/software-types', [App\Http\Controllers\SoftwareTypeController::class, 'store']);
    Route::patch('/software-types/{softwareType}', [App\Http\Controllers\SoftwareTypeController::class, 'update']);
    Route::delete('/software-types/{softwareType}', [App\Http\Controllers\SoftwareTypeController::class, 'destroy']);
    Route::get('/software-list', [App\Http\Controllers\SoftwareController::class, 'index']);
    Route::get('/company-software-list/{company}', [App\Http\Controllers\SoftwareController::class, 'companySoftwareList']);
    Route::get('/user-companies-software-list/{user}', [App\Http\Controllers\SoftwareController::class, 'userCompaniesSoftwareList']);
    Route::get('/form-field-software-list/{typeFormField}', [App\Http\Controllers\SoftwareController::class, 'formFieldSoftwareList']);
    Route::get('/software-list-full', [App\Http\Controllers\SoftwareController::class, 'softwareListWithTrashed']);
    Route::post('/software', [App\Http\Controllers\SoftwareController::class, 'store']);
    Route::get('/software/export-template', [App\Http\Controllers\SoftwareController::class, 'exportTemplate']);
    Route::get('/software-assign/export-template', [App\Http\Controllers\SoftwareController::class, 'exportAssignationTemplate']);
    Route::get('/software-delete/export-template', [App\Http\Controllers\SoftwareController::class, 'exportDeletionTemplate']);
    Route::get('/software/{software}', [App\Http\Controllers\SoftwareController::class, 'show']);
    Route::get('/fake-software-field', [App\Http\Controllers\SoftwareController::class, 'fakeSoftwareField']);
    Route::patch('/software/{software}', [App\Http\Controllers\SoftwareController::class, 'update']);
    Route::patch('/software-users/{software}', [App\Http\Controllers\SoftwareController::class, 'updateSoftwareUsers']); //lato software
    Route::delete('/software-user/{software}/{user}', [App\Http\Controllers\SoftwareController::class, 'deleteSoftwareUser']);
    Route::get('/user-software/{user}', [App\Http\Controllers\SoftwareController::class, 'userSoftwareList']); //lato utente
    Route::patch('/user-software/{user}', [App\Http\Controllers\SoftwareController::class, 'updateUserSoftware']);
    Route::delete('/software/{software}', [App\Http\Controllers\SoftwareController::class, 'destroy']);
    Route::post('/software-restore/{software}', [App\Http\Controllers\SoftwareController::class, 'restoreTrashed']);
    Route::delete('/software-trashed/{software}', [App\Http\Controllers\SoftwareController::class, 'destroyTrashed']);
    Route::post('/softwaremassive', [App\Http\Controllers\SoftwareController::class, 'importSoftware']);
    Route::post('/software-assign-massive', [App\Http\Controllers\SoftwareController::class, 'importSoftwareAssignations']);
    Route::post('/software-delete-massive', [App\Http\Controllers\SoftwareController::class, 'importSoftwareDeletions']);
    Route::get('software-user/{software}/{user}/download-assignment-pdf', [App\Http\Controllers\SoftwareController::class, 'downloadUserSoftwareAssignmentPdf']);
    Route::post('/delete-software-user', [App\Http\Controllers\SoftwareController::class, 'deleteSoftwareUser']); //rimuovi un'associazione utente-software
    Route::get('/software-tickets/{software}', [App\Http\Controllers\SoftwareController::class, 'softwareTickets']);
    Route::get('/software-logs/{software}', [App\Http\Controllers\SoftwareController::class, 'getSoftwareLog']);
    Route::get('/software-logs/{software}/export', [App\Http\Controllers\SoftwareController::class, 'softwareLogsExport']);
    Route::get('/software-export-all', [App\Http\Controllers\SoftwareController::class, 'exportAllSoftware']);
    Route::get('/software-export-company/{company}', [App\Http\Controllers\SoftwareController::class, 'exportCompanySoftware']);
    Route::get('/software/export-user/{user}', [App\Http\Controllers\SoftwareController::class, 'exportUserSoftware']);

    // Software Attachments Routes
    Route::get('/software/{software}/attachments', [App\Http\Controllers\SoftwareController::class, 'getAttachments']);
    Route::post('/software/{software}/attachments', [App\Http\Controllers\SoftwareController::class, 'uploadAttachment']);
    Route::post('/software/{software}/attachments/multiple', [App\Http\Controllers\SoftwareController::class, 'uploadAttachments']);
    Route::patch('/software/{software}/attachments/{attachment}', [App\Http\Controllers\SoftwareController::class, 'updateAttachment']);
    Route::delete('/software/{software}/attachments/{attachment}', [App\Http\Controllers\SoftwareController::class, 'deleteAttachment']);
    Route::post('/software/{software}/attachments/{attachment}/restore', [App\Http\Controllers\SoftwareController::class, 'restoreAttachment']);
    Route::delete('/software/{software}/attachments/{attachment}/force', [App\Http\Controllers\SoftwareController::class, 'forceDeleteAttachment']);
    Route::get('/software/{software}/attachments/{attachment}/download', [App\Http\Controllers\SoftwareController::class, 'getDownloadUrl']);
    Route::get('/software/{software}/attachments/{attachment}/preview', [App\Http\Controllers\SoftwareController::class, 'getPreviewUrl']);

    // Property Routes
    Route::get('/properties', [App\Http\Controllers\PropertyController::class, 'index']);
    Route::get('/properties/{property}', [App\Http\Controllers\PropertyController::class, 'show']);
    Route::post('/properties', [App\Http\Controllers\PropertyController::class, 'store']);
    Route::post('/properties/{property}/users', [App\Http\Controllers\PropertyController::class, 'addUser']);
    Route::post('/properties/{property}', [App\Http\Controllers\PropertyController::class, 'update']);
    Route::delete('/properties/{property}', [App\Http\Controllers\PropertyController::class, 'destroy']);

    // Document Routes
    Route::get('/documents', [App\Http\Controllers\DocumentController::class, 'index']);
    Route::post('/documents', [App\Http\Controllers\DocumentController::class, 'store']);
    Route::get('/documents/{document}/download', [App\Http\Controllers\DocumentController::class, 'downloadFile']);
    Route::delete('/documents/{document}', [App\Http\Controllers\DocumentController::class, 'destroy']);
    Route::get('/documents/search', [App\Http\Controllers\DocumentController::class, 'search']);
    Route::get('/documents/company/{companyId}/search', [App\Http\Controllers\DocumentController::class, 'searchByCompany']);

    // Help Routes

    Route::get('/files/public/search', [App\Http\Controllers\WikiObjectController::class, 'searchPublic']);
    Route::get('/files/public/{folder}', [App\Http\Controllers\WikiObjectController::class, 'public']);
    Route::get('/wiki-files/{wikiObject}', [App\Http\Controllers\WikiObjectController::class, 'downloadFile']);
    Route::get('/files/search', [App\Http\Controllers\WikiObjectController::class, 'search']);
    Route::get('/files/{folder}', [App\Http\Controllers\WikiObjectController::class, 'index']);
    Route::post('/files', [App\Http\Controllers\WikiObjectController::class, 'store']);

    // Stats Routes

    Route::get('/stats', [App\Http\Controllers\TicketStatsController::class, 'latestStats']);

    // Tenant Terms Routes (Authenticated)

    Route::get('/tenant-terms', [App\Http\Controllers\TenantTermController::class, 'index']);
    Route::get('/tenant-terms/category/{category}', [App\Http\Controllers\TenantTermController::class, 'byCategory']);
    Route::post('/tenant-terms', [App\Http\Controllers\TenantTermController::class, 'store']);
    Route::put('/tenant-terms/{key}', [App\Http\Controllers\TenantTermController::class, 'update']);
    Route::delete('/tenant-terms/{key}', [App\Http\Controllers\TenantTermController::class, 'destroy']);
    Route::get('/tenant-terms/{key}', [App\Http\Controllers\TenantTermController::class, 'show']);

    // Dashboard

    // Admin Dashboard
    Route::get('/admin/dashboard/card-config', [App\Http\Controllers\DashboardController::class, 'index']);
    Route::put('/admin/dashboard/card-config', [App\Http\Controllers\DashboardController::class, 'updateCardConfig']);

    // User Dashboard
    Route::get('/user/dashboard/card-config', [App\Http\Controllers\DashboardController::class, 'userIndex']);
    Route::get('/user/dashboard/news', [App\Http\Controllers\DashboardController::class, 'getNewsForUser']);
    Route::put('/user/dashboard/card-config', [App\Http\Controllers\DashboardController::class, 'updateUserCardConfig']);

    // News
    Route::get('/news/source/{newsSource}', [App\Http\Controllers\NewsController::class, 'bySource']);

    // Vertex AI Routes
    Route::post('/vertex-ai/generate-report', [App\Http\Controllers\VertexAiController::class, 'generateReportFromPrompt']);

    // Ticket Stages Management
    Route::apiResource('ticket-stages', App\Http\Controllers\TicketStageController::class);
    Route::post('/ticket-stages/{id}/restore', [App\Http\Controllers\TicketStageController::class, 'restore']);
    Route::get('/all-ticket-stages', [App\Http\Controllers\TicketStageController::class, 'all']);
    Route::get('/ticket-stages-options', [App\Http\Controllers\TicketStageController::class, 'options']);

    Route::get('/ticket-pro-forma-bill', [App\Http\Controllers\TicketProFormaBillController::class, 'index']);
    Route::get('/ticket-pro-forma-bill/list/{company}', [App\Http\Controllers\TicketProFormaBillController::class, 'companyProFormaBills']);
    // Route::get('/ticket-pro-forma-bill/{id}', [App\Http\Controllers\TicketProFormaBillController::class, 'show']);
    Route::post('/ticket-pro-forma-bill', [App\Http\Controllers\TicketProFormaBillController::class, 'store']);
    Route::post('/ticket-pro-forma-bill/regenerate', [App\Http\Controllers\TicketProFormaBillController::class, 'regenerate']);
    Route::patch('/ticket-pro-forma-bill', [App\Http\Controllers\TicketProFormaBillController::class, 'update']);
    Route::delete('/ticket-pro-forma-bill/{proFormaBill}', [App\Http\Controllers\TicketProFormaBillController::class, 'destroy']);
    Route::get('/ticket-pro-forma-bill/preview/{proFormaBill}', [App\Http\Controllers\TicketProFormaBillController::class, 'pdfPreview']);
    Route::get('/ticket-pro-forma-bill/download/{proFormaBill}', [App\Http\Controllers\TicketProFormaBillController::class, 'pdfDownload']);
});
