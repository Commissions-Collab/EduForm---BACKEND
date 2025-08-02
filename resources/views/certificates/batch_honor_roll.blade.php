@foreach ($qualified as $item)
    @php
        $student = $item['student'];
        $data = $item['data'];
    @endphp

    @include('certificates.honor_roll', ['student' => $student, 'data' => $data])

    <div style="page-break-after: always;"></div>
@endforeach
