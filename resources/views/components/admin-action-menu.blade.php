@props(['editUrl' => null, 'deleteAction' => null, 'deleteConfirm' => 'Apakah Anda yakin ingin menghapus data ini?'])
<div class="sl-menu-wrap" data-sl-menu>
    <button type="button" class="sl-menu-btn" aria-haspopup="true" aria-expanded="false" title="Aksi">&#8942;</button>
    <div class="sl-menu" role="menu">
        @if ($editUrl)
            <a href="{{ $editUrl }}" role="menuitem">✏️ Edit</a>
        @endif
        @if ($deleteAction)
            <form method="POST" action="{{ $deleteAction }}" onsubmit="return confirm('{{ $deleteConfirm }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="danger" role="menuitem">🗑️ Hapus</button>
            </form>
        @endif
        {{ $slot ?? '' }}
    </div>
</div>
