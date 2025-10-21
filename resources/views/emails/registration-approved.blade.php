<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #3730A3 0%, #2C268C 100%); color: white; padding: 30px; text-align: center; border-radius: 5px 5px 0 0;">
        <h1 style="margin: 0; font-size: 28px;">🎓 AcadFlow</h1>
        <p style="margin: 10px 0 0 0;">Your pathway to knowledge</p>
    </div>

    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #e0e0e0; border-radius: 0 0 5px 5px;">
        <h2 style="color: #3730A3; margin-top: 0;">Welcome to AcadFlow! 🎉</h2>
        
        <p>Great news! Your student registration has been <strong>approved</strong> by the school administrator.</p>
        
        <p>Your account is now active. You can log in with the following details:</p>
        <ul style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #3730A3;">
            <li><strong>Email:</strong> {{ $user->email }}</li>
            <li><strong>Password:</strong> The password you created during registration</li>
        </ul>

        <p style="margin-top: 25px;">Click the button below to log in to your account:</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $loginUrl }}" style="display: inline-block; background: #3730A3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Go to Login
            </a>
        </div>

        <p style="font-size: 13px; color: #666; margin-top: 20px;">
            Or copy and paste this link in your browser:<br>
            <span style="word-break: break-all; color: #3730A3;">{{ $loginUrl }}</span>
        </p>

        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">

        <p style="font-size: 12px; color: #666;">
            If you didn't register for this account, please ignore this email.<br>
            © 2025 AcadFlow. All rights reserved.
        </p>
    </div>
</div>