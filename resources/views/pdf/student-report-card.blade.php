<!DOCTYPE html>
<html>
<head>
    <title>Student Report Card</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        .page-break {
            page-break-after: always;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .info {
            margin-bottom: 10px;
        }

        .info strong {
            display: inline-block;
            width: 120px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table, th, td {
            border: 1px solid #000;
        }

        th, td {
            padding: 6px;
            text-align: center;
        }

        .final {
            margin-top: 20px;
            font-weight: bold;
        }

    </style>
</head>
<body>

    <div class="header">
        <h2>Student Report Card</h2>
        <p>Academic Year: {{ date('Y') }} - {{ date('Y') + 1 }}</p>
    </div>

    <div class="info">
        <p><strong>Student Name:</strong> {{ $student }}</p>
        <p><strong>Student ID:</strong> {{ $student_id }}</p>
        <p><strong>Section:</strong> {{ $section }}</p>
    </div>

    @foreach ($quarters as $quarter)
        <h3>{{ $quarter['quarter'] }}</h3>
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Average Grade</th>
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
        <br>
    @endforeach

    <p class="final">Final Average: {{ $final_average }}</p>

</body>
</html>
