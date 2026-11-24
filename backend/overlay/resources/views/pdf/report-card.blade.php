<!doctype html><html><head><meta charset="utf-8">@include('pdf._style')</head><body>
@include('pdf._header')
<div class="doc-title">STUDENT REPORT CARD &mdash; {{ strtoupper($term->name) }}, {{ $session->name }} SESSION</div>

<table style="margin-bottom:8px"><tr>
    <td class="box" style="width:50%">
        <div class="label">Student</div><strong style="font-size:12px">{{ $student->first_name }} {{ $student->other_name }} {{ $student->last_name }}</strong><br>
        <span class="muted">Admission no:</span> {{ $student->admission_no }}<br>
        <span class="muted">Class:</span> {{ $student->schoolClass->name }} {{ $student->classArm->name ?? '' }}<br>
        <span class="muted">Gender:</span> {{ ucfirst($student->gender) }}
    </td>
    <td style="width:2%"></td>
    <td class="box">
        <span class="muted">Subjects offered:</span> {{ $summary->subjects_count ?? $rows->count() }}<br>
        <span class="muted">Total score:</span> {{ number_format((float)($summary->total_score ?? 0), 1) }}<br>
        <span class="muted">Average:</span> <strong>{{ number_format((float)($summary->average ?? 0), 1) }}%</strong><br>
        <span class="muted">Position in class:</span> <strong>{{ $summary->position ?? '-' }}</strong> of {{ $summary->class_size ?? '-' }}<br>
        <span class="muted">Attendance:</span> {{ $attendance['attended'] }} of {{ $attendance['marked'] }} days
    </td>
</tr></table>

<table class="grid">
    <thead><tr><th>Subject</th><th class="c">CA ({{ (int)($school['max_ca_score'] ?? 40) }})</th><th class="c">Exam ({{ (int)($school['max_exam_score'] ?? 60) }})</th><th class="c">Total (100)</th><th class="c">Grade</th><th>Remark</th><th class="c">Pos.</th></tr></thead>
    <tbody>
    @foreach($rows as $r)
        <tr><td>{{ $r->subject->name }}</td><td class="c">{{ rtrim(rtrim(number_format((float)$r->ca_score, 1), '0'), '.') }}</td><td class="c">{{ rtrim(rtrim(number_format((float)$r->exam_score, 1), '0'), '.') }}</td>
            <td class="c"><strong>{{ rtrim(rtrim(number_format((float)$r->total, 1), '0'), '.') }}</strong></td><td class="c"><strong>{{ $r->grade }}</strong></td><td>{{ $r->remark }}</td><td class="c">{{ $r->position }}</td></tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top:8px"><tr>
    <td class="box" style="width:60%"><div class="label">Class teacher's comment</div>{{ $summary->teacher_comment ?? '—' }}<br><br>
        <div class="label">Principal's comment</div>{{ $summary->principal_comment ?? '—' }}</td>
    <td style="width:2%"></td>
    <td class="box"><div class="label">Grading key</div>
        @foreach($scale as $g)<span>{{ $g->min_score }}-{{ $g->max_score }} = <strong>{{ $g->grade }}</strong> {{ $g->remark }}</span><br>@endforeach
    </td>
</tr></table>

@if(!empty($school['next_term_begins']))<p><strong>Next term begins:</strong> {{ \Carbon\Carbon::parse($school['next_term_begins'])->format('l, j F Y') }}</p>@endif

<table class="sign"><tr><td><span>Class Teacher</span></td><td><span>{{ $school['principal_name'] ?: 'Principal' }}</span></td><td><span>Parent / Guardian</span></td></tr></table>
</body></html>
