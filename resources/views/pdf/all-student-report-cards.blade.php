@foreach ($students as $data)
    <div style="page-break-after: always;">
        <h2>Report Card</h2>
        <p><strong>Name:</strong> {{ $data['student'] }}</p>
        <p><strong>ID:</strong> {{ $data['student_id'] }}</p>
        <p><strong>Section:</strong> {{ $data['section'] }}</p>

        @foreach ($data['quarters'] as $quarter)
            <h3>{{ $quarter['quarter'] }}</h3>
            <table width="100%" border="1" cellspacing="0" cellpadding="5">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quarter['grades'] as $grade)
                        <tr>
                            <td>{{ $grade['subject'] }}</td>
                            <td>{{ $grade['average'] }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>Quarter Average</strong></td>
                        <td><strong>{{ $quarter['quarter_average'] }}</strong></td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <p><strong>Final Average:</strong> {{ $data['final_average'] }}</p>
    </div>
@endforeach
