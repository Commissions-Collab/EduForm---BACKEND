<!DOCTYPE html>
<html>
<head>
    <title>Honor Roll Certificate</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; text-align: center; padding: 50px; }
        .certificate-box {
            border: 10px solid #8e44ad;
            padding: 50px;
            border-radius: 20px;
        }
        h1 { font-size: 36px; color: #8e44ad; margin-bottom: 10px; }
        h2 { font-size: 28px; margin-bottom: 20px; }
        p { font-size: 18px; }
        .name { font-size: 28px; font-weight: bold; margin-top: 30px; }
        .footer { margin-top: 50px; font-size: 16px; }
    </style>
</head>
<body>
    <div class="certificate-box">
        <h1>Honor Roll Certificate</h1>
        <p>This certificate is awarded to</p>
        <div class="name">{{ $student->fullName() }}</div>
        <p>for achieving the distinction of</p>
        <h2>{{ $data['honor_type'] }}</h2>
        <p>with a general average of <strong>{{ $data['grade_average'] }}</strong> during {{ $data['quarter'] }}</p>

        <div class="footer">
            Academic Year: {{ $data['academic_year'] }}<br>
            Issued on: {{ now()->format('F d, Y') }}
        </div>
    </div>
</body>
</html>
