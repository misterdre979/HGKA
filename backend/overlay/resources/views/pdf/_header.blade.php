<table class="head"><tr>
    <td style="width:60px"><img src="{{ $logo }}" style="width:52px;height:52px"></td>
    <td>
        <div class="school">{{ $school['school_name'] ?? 'His Grace Kiddies Academy' }}</div>
        @if(!empty($school['motto']))<div class="muted" style="font-style:italic">{{ $school['motto'] }}</div>@endif
        <div class="muted">{{ $school['address'] ?? '' }} @if(!empty($school['phone']))&middot; {{ $school['phone'] }}@endif @if(!empty($school['email']))&middot; {{ $school['email'] }}@endif</div>
    </td>
</tr></table>
