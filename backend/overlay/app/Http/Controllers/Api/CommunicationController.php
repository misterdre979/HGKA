<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommunicationController extends Controller
{
    public function __construct(private NotificationService $notify)
    {
    }

    // ---------------------------------------------------------------- announcements
    /** Announcements visible to the signed-in user (by audience role, publish and expiry dates). */
    public static function visibleAnnouncements(User $user, int $limit = 50)
    {
        $roles = $user->roleSlugs();
        $manager = $user->hasPermission('communication.send');

        return Announcement::with('createdBy:id,name')
            ->when(! $manager, fn ($q) => $q->where('published_at', '<=', now())->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->orderByDesc('is_pinned')->orderByDesc('published_at')->limit($limit)->get()
            ->filter(fn ($a) => $manager || empty($a->audience) || count(array_intersect($a->audience, $roles)) > 0)->values();
    }

    public function announcements(Request $request): JsonResponse
    {
        return response()->json(self::visibleAnnouncements($request->user()));
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['nullable', 'array'],
            'audience.*' => ['string', Rule::exists('roles', 'slug')],
            'priority' => ['nullable', Rule::in(['normal', 'important', 'urgent'])],
            'is_pinned' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'notify' => ['boolean'],
            'channels' => ['array'],
            'channels.*' => [Rule::in(['app', 'email', 'sms'])],
        ]);

        $announcement = Announcement::create([
            'title' => $d['title'], 'body' => $d['body'], 'audience' => $d['audience'] ?? [],
            'priority' => $d['priority'] ?? 'normal', 'is_pinned' => $d['is_pinned'] ?? false,
            'published_at' => now(), 'expires_at' => $d['expires_at'] ?? null, 'created_by' => $request->user()->id,
        ]);

        $sent = 0;
        if ($d['notify'] ?? true) {
            $channels = $d['channels'] ?? ['app'];
            $users = User::where('is_active', true)
                ->when(! empty($d['audience']), fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('slug', $d['audience'])))->get();
            foreach ($users as $u) {
                if (in_array('app', $channels, true)) {
                    $this->notify->toUser($u, $d['title'], mb_substr($d['body'], 0, 240), 'announcement', ['announcement_id' => $announcement->id]);
                }
                if (in_array('email', $channels, true) && $u->email) {
                    $this->notify->email($u->email, $d['title'], $d['body']);
                }
                if (in_array('sms', $channels, true) && $u->phone) {
                    $this->notify->sms($u->phone, $d['title'].': '.mb_substr($d['body'], 0, 120));
                }
                $sent++;
            }
        }
        Audit::log('announcements.created', $announcement, null, ['recipients' => $sent]);

        return response()->json(['announcement' => $announcement->load('createdBy:id,name'), 'recipients' => $sent], 201);
    }

    public function destroyAnnouncement(Announcement $announcement): JsonResponse
    {
        $announcement->delete();
        Audit::log('announcements.deleted', $announcement);

        return response()->json(['message' => 'Announcement removed.']);
    }

    // ---------------------------------------------------------------- notifications
    public function notifications(Request $request): JsonResponse
    {
        return response()->json(
            AppNotification::where('user_id', $request->user()->id)->orderByDesc('id')->paginate(min((int) $request->query('per_page', 20), 100))
        );
    }

    public function counts(Request $request): JsonResponse
    {
        $id = $request->user()->id;

        return response()->json([
            'notifications' => AppNotification::where('user_id', $id)->whereNull('read_at')->count(),
            'messages' => Message::where('recipient_id', $id)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403, 'You do not have permission to perform this action.');
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    // ---------------------------------------------------------------- messages
    public function inbox(Request $request): JsonResponse
    {
        return response()->json(
            Message::with('sender:id,name')->where('recipient_id', $request->user()->id)->orderByDesc('id')->paginate(20)
        );
    }

    public function sent(Request $request): JsonResponse
    {
        return response()->json(
            Message::with('recipient:id,name')->where('sender_id', $request->user()->id)->orderByDesc('id')->paginate(20)
        );
    }

    public function showMessage(Request $request, Message $message): JsonResponse
    {
        $uid = $request->user()->id;
        abort_unless(in_array($uid, [$message->sender_id, $message->recipient_id], true), 403, 'You do not have permission to perform this action.');

        if ($message->recipient_id === $uid && ! $message->read_at) {
            $message->update(['read_at' => now()]);
        }
        $root = $message->parent_id ?: $message->id;
        $thread = Message::with(['sender:id,name', 'recipient:id,name'])
            ->where(fn ($q) => $q->where('id', $root)->orWhere('parent_id', $root))
            ->where(fn ($q) => $q->where('sender_id', $uid)->orWhere('recipient_id', $uid))->orderBy('id')->get();

        return response()->json(['message' => $message->load(['sender:id,name', 'recipient:id,name']), 'thread' => $thread]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $d = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id', 'different:'.$request->user()->id],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);
        $sender = $request->user();
        $recipient = User::where('is_active', true)->findOrFail($d['recipient_id']);

        // Students and parents may only write to school staff, never to each other.
        if ($sender->hasRole('student', 'parent') && $recipient->hasRole('student', 'parent')) {
            abort(403, 'You can only send messages to school staff.');
        }

        $message = Message::create([
            'sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'subject' => $d['subject'] ?? null,
            'body' => $d['body'], 'parent_id' => $d['parent_id'] ?? null,
        ]);
        $this->notify->toUser($recipient, 'New message from '.$sender->name, mb_substr($d['body'], 0, 140), 'message', ['message_id' => $message->id]);

        return response()->json($message->load('recipient:id,name'), 201);
    }

    /** People the user may message. Students/parents see school staff only. */
    public function recipients(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = User::where('is_active', true)->where('id', '!=', $user->id)->with('roles:id,name');

        if ($user->hasRole('student', 'parent')) {
            $q->whereHas('roles', fn ($r) => $r->whereNotIn('slug', ['student', 'parent']));
        }
        if (($s = trim((string) $request->query('q', ''))) !== '') {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }

        return response()->json($q->orderBy('name')->limit(30)->get()->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'roles' => $u->roles->pluck('name')->implode(', '),
        ]));
    }
}
