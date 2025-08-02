@foreach ($qualified as $item)
    @php
        $student = $item['student'];
        $data = $item['data'];
    @endphp

    @include('certificates.perfect_attendance', ['student' => $student, 'data' => $data])

    <div style="page-break-after: always;"></div>
@endforeach
