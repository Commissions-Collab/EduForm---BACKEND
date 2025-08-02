<!DOCTYPE html>
<html>
<head>
    <title>Perfect Attendance Certificate</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; text-align: center; padding: 50px; }
        .certificate-box {
            border: 10px solid #4a90e2;
            padding: 50px;
            border-radius: 20px;
        }
        h1 { font-size: 36px; color: #4a90e2; margin-bottom: 10px; }
        h2 { font-size: 28px; margin-bottom: 20px; }
        p { font-size: 18px; }
        .name { font-size: 28px; font-weight: bold; margin-top: 30px; }
        .footer { margin-top: 50px; font-size: 16px; }
    </style>
</head>
<body>
    <div class="certificate-box">
        <h1>Certificate of Perfect Attendance</h1>
        <p>This certificate is proudly presented to</p>
        <div class="name">{{ $student->fullName() }}</div>
        <p>for having <strong>100% attendance</strong> during the following quarter(s):</p>
        <h2>{{ $data['quarters'] }}</h2>

        <div class="footer">
            Academic Year: {{ $data['academic_year'] }}<br>
            Issued on: {{ now()->format('F d, Y') }}
        </div>
    </div>
</body>
</html>
