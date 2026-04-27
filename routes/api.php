<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\AreaController\AreaController;
use App\Http\Controllers\Clients\ClientsController;
use App\Http\Controllers\TargetController\TargetController;
use App\Http\Controllers\Settings\BackofficeController;
use App\Http\Controllers\Settings\BusinessEntityController;
use App\Http\Controllers\GroupControllers\GroupController;
use App\Http\Controllers\Integrations\MeetingRecorderController;
use App\Http\Controllers\MappingController\MappingController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\KamProductMappingController;
use App\Http\Controllers\Settings\ApprovalPipelineStepController;
use App\Http\Controllers\Settings\LeadPipelineStageController;
use App\Http\Controllers\Settings\SettingController;
use App\Http\Controllers\Settings\SocialConnectionController;
use App\Http\Controllers\Settings\SystemAccountConnectionController;
use App\Http\Controllers\TeamControllers\TeamController;
use App\Http\Controllers\EntityColumnMappingController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\NavigationItemController;
use App\Http\Controllers\UserMappingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;


function extractCleanText(?string $html, ?string $plain): string
{
    // ✅ Plain আগে check করো — সবচেয়ে accurate
    if ($plain && trim($plain) !== '') {
        $text = $plain;
    } elseif ($html && trim($html) !== '') {
        // Plain নেই → HTML থেকে extract করো
        $text = preg_replace('/<(br\s*\/?|\/p|\/div|\/li|\/tr)\s*>/i', "\n", $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } else {
        return '';
    }

    // ✅ Normalize newline
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // ✅ Remove quoted lines (>)
    $lines = explode("\n", $text);
    $lines = array_filter($lines, fn($l) => !preg_match('/^\s*>/', $l));
    $text  = implode("\n", $lines);

    // ✅ Remove reply thread
    $text = preg_split('/\nOn .+?wrote:/si', $text)[0];

    // ✅ Remove forwarded mail
    $text = preg_split('/\nFrom:\s+[^\n]+\nSent:/i', $text)[0];

    // ✅ Remove unsubscribe footer
    $text = preg_split('/\nTo unsubscribe from this group/i', $text)[0];

    // ✅ Remove common signatures
    $text = preg_split('/\nRegards,|\nBest regards,|\nThanks & Regards/i', $text)[0];

    // ✅ Remove mobile signature
    $text = preg_split('/\nSent from my/i', $text)[0];

    // ✅ Remove markdown-style *text*
    $text = preg_replace('/\*(.*?)\*/', '$1', $text);

    // ✅ Remove remaining standalone *
    $text = str_replace('*', '', $text);

    // ✅ Normalize spaces & tabs
    $text = preg_replace('/[ \t]+/', ' ', $text);

    // ✅ Normalize multiple newlines
    $text = preg_replace('/\n{2,}/', "\n", $text);

    // ✅ Trim each line
    $lines = array_map('trim', explode("\n", $text));
    $text  = implode("\n", $lines);

    return trim(preg_replace('/\s+/', ' ', $text));
}



Route::get('/test-imap', function () {


    $client = Client::account('default');
    $client->connect();
    $folder = $client->getFolder('INBOX');

    $messages = $folder->messages()
        ->seen()
        ->setFetchOrder('desc')
        ->limit(1)
        ->get();

    $results = [];

    foreach ($messages as $message) {

        $uid     = $message->getUid();
        $from    = $message->getFrom()[0]->mail ?? null;
        $name    = trim($message->getFrom()[0]->personal ?? '', '"');
        $subject = $message->getSubject()->toString();
        $date    = $message->getDate()->first();

        // ✅ Message ID fix (VERY IMPORTANT)
        $messageId = $message->getMessageId()?->toString();
        $messageId = $messageId ? '<' . trim($messageId, '<>') . '>' : null;

        // optional
        $inReplyTo = $message->getInReplyTo();
        $inReplyTo = $inReplyTo ? $inReplyTo[0] : null;

        $html  = $message->getHTMLBody();
        $plain = $message->getTextBody();

        // ✅ fallback HTML
        if (!$html && $plain) {
            $html = '<pre style="font-family:inherit;white-space:pre-wrap;">'
                  . htmlspecialchars($plain, ENT_QUOTES, 'UTF-8')
                  . '</pre>';
        }

        // ✅ clean text
        $text = extractCleanText($html, $plain);



        // =========================
        // 🔁 AUTO REPLY
        // =========================
        $replySubject = str_starts_with($subject, 'Re:')
            ? $subject
            : 'Re: ' . $subject;

        $greeting = $name ? "Dear $name," : "Dear Sir/Madam,";

        $replyMessage = "$greeting\n\nThank you for your email. We have received your message.\n\nBest regards.";

        // 🔥 NEW UNIQUE MESSAGE-ID (VERY IMPORTANT)



// =====================

$messageId = trim($messageId, '<>');
$newMessageId = bin2hex(random_bytes(16)) . '@race.net.bd';

Mail::send([], [], function ($mail) use ($from, $replySubject, $replyMessage, $messageId) {

    $mail->to($from)
         ->subject($replySubject);

    // body
    $mail->text($replyMessage);

    // 🔥 CORRECT WAY TO ADD HEADERS
    $headers = $mail->getSymfonyMessage()->getHeaders();

    $headers->addTextHeader('In-Reply-To', $messageId);
    $headers->addTextHeader('References', $messageId);
});

        $message->setFlag('Seen');

        $results[] = [
            'uid'        => $uid,
            'from'       => $from,
            'name'       => $name,
            'subject'    => $subject,
            'date'       => $date,
            'message_id' => $messageId,
            'text'       => $text,
        ];
    }

    return response()->json($results);
});

// Route::get('/test-imap', function () {

//     $client = Client::account('default');
//     $client->connect();
//     $folder = $client->getFolder('INBOX');

//     $messages = $folder->messages()
//         ->seen()
//         ->setFetchOrder('desc')
//         ->limit(1)
//         ->get();

//     $results = [];

//     foreach ($messages as $message) {

//         $uid     = $message->getUid();
//         $from    = $message->getFrom()[0]->mail ?? null;
//         $name    = trim($message->getFrom()[0]->personal ?? '', '"');
//         $subject = $message->getSubject()->toString();
//         $date    = $message->getDate()->first();

//         $html  = $message->getHTMLBody();
//         $plain = $message->getTextBody();

//         // ✅ HTML — plain নেই হলে wrap করো
//         if (!$html && $plain) {
//             $html = '<pre style="font-family:inherit;white-space:pre-wrap;">'
//                   . htmlspecialchars($plain, ENT_QUOTES, 'UTF-8')
//                   . '</pre>';
//         }

//         // ✅ Clean text + preview
//         $text    = extractCleanText($html, $plain);

//         // // ✅ DB Insert
//         // $exists = DB::table('emails')->where('uid', $uid)->exists();

//         // if (!$exists) {
//         //     DB::table('emails')->insert([
//         //         'uid'          => $uid,
//         //         'from_email'   => $from,
//         //         'from_name'    => $name,
//         //         'subject'      => $subject,
//         //         'body_html'    => $html,     // display
//         //         'body_text'    => $text,     // AI / search
//         //         'status'       => 'unread',
//         //         'received_at'  => $date,
//         //         'created_at'   => now(),
//         //         'updated_at'   => now(),
//         //     ]);
//         // }



//     $replyMessage = "Dear Sir,\n\nYour email has been received.\n\nBest regards.";



//         Mail::raw($replyMessage, function ($mail) use ($from, $subject) {
//             $mail->to($from)
//                  ->subject('Re: ' . $subject);
//         });

//         $message->setFlag('Seen');

//         $results[] = [
//             'uid'     => $uid,
//             'from'    => $from,
//             'name'    => $name,
//             'subject' => $subject,
//             'date'    => $date,
//             // 'html'    => $html,    // detail view এ React render
//             'text'    => $text,    // AI / WhatsApp forward
//             // 'saved'   => !$exists,
//         ];
//     }

//     return response()->json($results);
// });


Route::prefix('system')->group(function (): void {

    Route::post('/permissions/sync', function () {

        Artisan::call('permissions:sync');

        return response()->json([
            'message' => 'Permissions synced successfully',
            'data' => true
        ]);

    })
    ->middleware(['auth:api', 'permission']);

});

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function (): void {


    Route::post('/feature-action-permissions', [NavigationItemController::class, 'storeFeatureAction']);
    Route::get('/feature-action-permissions/{user_view_id}', [NavigationItemController::class, 'showFeatureAction']);
    Route::put('/feature-action-permissions/{user_view_id}', [NavigationItemController::class, 'updateFeatureAction']);
    Route::get('/navigation-items/active', [NavigationItemController::class, 'getActiveItems']);
    Route::get('/navigation-features/{navigation_id}', [NavigationItemController::class, 'getByNavigationId']);
    Route::post('/user-view-permissions', [NavigationItemController::class, 'store']);
    Route::get('/user-view-permissions', [NavigationItemController::class, 'show']);
    Route::put('/user-view-permissions', [NavigationItemController::class, 'update']);


    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/change-password', [ProfileController::class, 'changePassword']);
    Route::post('integrations/meeting-recorder/launch', [MeetingRecorderController::class, 'launch']);

    Route::prefix('areas')->controller(AreaController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/divisions/{division}/districts', 'districts');
        Route::get('/districts/{district}/thanas', 'thanas');
        Route::post('/', 'store');
        Route::put('/{type}/{id}', 'update');
        Route::patch('/{type}/{id}', 'update');
        Route::delete('/{type}/{id}', 'destroy');
    });

    Route::prefix('clients')->controller(ClientsController::class)->group(function (): void {
        Route::get('/', 'index')->defaults('permission', 'clients.view')
        ->middleware('permission');
        Route::post('/', 'store') ->defaults('permission', 'clients.create')
        ->middleware('permission');
        Route::get('/{client}', 'show')->defaults('permission', 'clients.view')
        ->middleware('permission');
        Route::put('/{client}', 'update')->defaults('permission', 'clients.update')
        ->middleware('permission');
        Route::patch('/{client}', 'update')->defaults('permission', 'clients.update')
        ->middleware('permission');
        Route::delete('/{client}', 'destroy')->defaults('permission', 'clients.delete')
        ->middleware('permission');
    });

    Route::prefix('system')->controller(SettingController::class)->group(function (): void {
        Route::get('/roles', 'rolesIndex');
        Route::get('/access-control', 'accessControlIndex');
        Route::post('/roles', 'storeRole');
        Route::put('/roles/{role}', 'updateRole');
        Route::patch('/roles/{role}', 'updateRole');
        Route::delete('/roles/{role}', 'destroyRole');

        Route::get('/users', 'usersIndex');
        Route::post('/users', 'storeUser');
        Route::put('/users/{systemUser}', 'updateUser');
        Route::patch('/users/{systemUser}', 'updateUser');
        Route::delete('/users/{systemUser}', 'destroyUser');

    });

    Route::prefix('system')->controller(RoleController::class)->group(function (): void {
        Route::get('/roles/{role}/permissions', 'rolePermission');
        Route::post('/roles/{role}/update-permissions', 'updateRolePermissions');
    });

    Route::prefix('system')->group(function (): void {
        Route::get('/backoffice/options', [BackofficeController::class, 'options']);
        Route::get('/backoffice', [BackofficeController::class, 'index']);
        Route::post('/backoffice', [BackofficeController::class, 'store']);
        Route::put('/backoffice/{backoffice}', [BackofficeController::class, 'update']);
        Route::patch('/backoffice/{backoffice}', [BackofficeController::class, 'update']);
        Route::delete('/backoffice/{backoffice}', [BackofficeController::class, 'destroy']);

        Route::get('/business-entities', [BusinessEntityController::class, 'index']);
        Route::post('/business-entities', [BusinessEntityController::class, 'store']);
        Route::put('/business-entities/{businessEntity}', [BusinessEntityController::class, 'update']);
        Route::patch('/business-entities/{businessEntity}', [BusinessEntityController::class, 'update']);
        Route::delete('/business-entities/{businessEntity}', [BusinessEntityController::class, 'destroy']);

        Route::get('/kam-mappings/options', [KamProductMappingController::class, 'options']);
        Route::get('/business-entities/{businessEntity}/products', [KamProductMappingController::class, 'products']);
        Route::get('/kam-mappings/list', [KamProductMappingController::class, 'index']);
        Route::get('/kam-mappings', [KamProductMappingController::class, 'show']);
        Route::post('/kam-mappings', [KamProductMappingController::class, 'store']);

        Route::get('/lead-pipeline-stages/options', [LeadPipelineStageController::class, 'options']);
        Route::get('/lead-pipeline-stages', [LeadPipelineStageController::class, 'show']);
        Route::post('/lead-pipeline-stages', [LeadPipelineStageController::class, 'store']);

        Route::get('/social-connections', [SocialConnectionController::class, 'index']);
        Route::post('/social-connections', [SocialConnectionController::class, 'store']);
        Route::patch('/social-connections/{channelConnection}/activate', [SocialConnectionController::class, 'activate']);
        Route::patch('/social-connections/{channelConnection}/deactivate', [SocialConnectionController::class, 'deactivate']);
        Route::delete('/social-connections/{channelConnection}', [SocialConnectionController::class, 'destroy']);

        Route::get('/approval-pipeline-steps/options', [ApprovalPipelineStepController::class, 'options']);
        Route::get('/approval-pipeline-steps', [ApprovalPipelineStepController::class, 'show']);
        Route::post('/approval-pipeline-steps', [ApprovalPipelineStepController::class, 'store']);

        Route::get('/external-systems', [SystemAccountConnectionController::class, 'externalSystemsIndex']);
        Route::get('/external-systems/{externalSystem}/users', [SystemAccountConnectionController::class, 'externalSystemUsers']);
        Route::get('/users/{systemUser}/external-account-connections', [SystemAccountConnectionController::class, 'showUserConnections']);
        Route::post('/users/{systemUser}/external-account-connections', [SystemAccountConnectionController::class, 'storeUserConnections']);

        Route::get('/teams', [TeamController::class, 'index']);
        Route::post('/teams', [TeamController::class, 'store']);
        Route::put('/teams/{team}', [TeamController::class, 'update']);
        Route::patch('/teams/{team}', [TeamController::class, 'update']);
        Route::delete('/teams/{team}', [TeamController::class, 'destroy']);

        Route::get('/groups', [GroupController::class, 'index']);
        Route::post('/groups', [GroupController::class, 'store']);
        Route::put('/groups/{group}', [GroupController::class, 'update']);
        Route::patch('/groups/{group}', [GroupController::class, 'update']);
        Route::delete('/groups/{group}', [GroupController::class, 'destroy']);

        Route::post('/user-mappings', [MappingController::class, 'store']);
    });

    Route::prefix('entity-column-mappings')->group(function () {

        Route::get('/get-navigation-items', [EntityColumnMappingController::class, 'getNavigationItems']);
        Route::get('/get-table-items', [EntityColumnMappingController::class, 'getTableItems']);
        Route::get('/get-column-items', [EntityColumnMappingController::class, 'getColumnItems']);

        Route::get('/table-column-mappings', [EntityColumnMappingController::class, 'getEntityWisetableColumnMappings']);


        Route::get('/', [EntityColumnMappingController::class, 'index']);
        Route::post('/', [EntityColumnMappingController::class, 'store']);
        Route::get('/{id}', [EntityColumnMappingController::class, 'show']);
        Route::put('/', [EntityColumnMappingController::class, 'update']);
        // Route::delete('/{id}', [EntityColumnMappingController::class, 'destroy']);

        Route::post('/bulk', [EntityColumnMappingController::class, 'storeBulk']);
        Route::delete('/{id}', [EntityColumnMappingController::class, 'destroy']);
        Route::delete('/delete-by-criteria', [EntityColumnMappingController::class, 'destroyByCriteria']);


});

    Route::prefix('target')->controller(TargetController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{target}', 'show');
        Route::put('/{target}', 'update');
        Route::patch('/{target}', 'update');
        Route::delete('/{target}', 'destroy');
    });

    Route::prefix('leads')->controller(LeadController::class)->group(function (): void {
        Route::get('/options', 'options');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{lead}', 'show');
        Route::put('/{lead}', 'update');
        Route::patch('/{lead}', 'update');
        Route::delete('/{lead}', 'destroy');
    });

    Route::prefix('tasks')->controller(TaskController::class)->group(function (): void {
        Route::get('/options', 'options');
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{task}', 'show');
        Route::get('/note-attachments/{taskNoteAttachment}', 'downloadNoteAttachment');
        Route::put('/{task}', 'update');
        Route::patch('/{task}', 'update');
        Route::post('/{task}/check-in', 'checkIn');
        Route::post('/{task}/complete', 'complete');
        Route::post('/{task}/cancel', 'cancel');
        Route::post('/{task}/notes', 'storeNote');
        Route::delete('/{task}', 'destroy');
    });

    Route::prefix('user-mappings')->group(function () {
        Route::post('/clients/by-business-entity', [UserMappingController::class, 'getClientsByBusinessEntity']);
        Route::get('/divisions', [UserMappingController::class, 'getDivisions']);
        Route::post('/store', [UserMappingController::class, 'storeUserMappings']);
        Route::get('/{userId}', [UserMappingController::class, 'getUserMappings']);
    });
});
