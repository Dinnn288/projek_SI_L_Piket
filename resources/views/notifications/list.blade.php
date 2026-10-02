@forelse ($notifications as $notification)
    <form method="POST" action="{{ $readRoute($notification) }}" style="display:block; margin-bottom:12px;">
        @csrf
        <button type="submit" style="display:block; width:100%; text-align:left; background:{{ $notification->is_read ? '#fff' : '#eff6ff' }}; border:1px solid var(--border); border-radius:8px; padding:14px 16px; cursor:pointer;">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                <strong>{{ $notification->title }}</strong>
                @if (! $notification->is_read)
                    <span class="badge badge-proses">Baru</span>
                @endif
            </div>
            <div style="color:var(--muted); margin-top:4px;">{{ $notification->message }}</div>
            <small style="color:var(--muted);">{{ $notification->created_at?->format('d M Y H:i') }}{{ $notification->is_read ? '' : ' • belum dibaca' }}</small>
        </button>
    </form>
@empty
    <p class="empty">Belum ada notifikasi.</p>
@endforelse
<x-pagination :paginator="$notifications" />
