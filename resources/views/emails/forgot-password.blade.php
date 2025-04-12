<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f5f5f5;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <!-- Email Container -->
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); max-width: 600px;">
                    <!-- Header -->
                    <tr>
                        <td align="center" bgcolor="#1a237e" style="padding: 24px; color: white; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                            <!-- Logo -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 16px;">
                                        <img src="cid:logo.png" alt="NPG Solutions Logo" style="display: block;">
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <h1 style="margin: 0; font-size: 24px; font-weight: bold;">Reset Your Password</h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px 24px; color: #333333;">
                            <!-- Greeting -->
                            <p style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Hi {{ $user->first_name }},</p>

                            <!-- Message -->
                            <p style="font-size: 16px; line-height: 1.6; margin-bottom: 24px;">You requested to reset your password. Click the button below to reset it:</p>

                            <!-- Reset Button -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 32px 0;">
                                <tr>
                                    <td align="center">
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" bgcolor="#1a237e" style="border-radius: 6px;">
                                                    <a href="{{ $resetUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; font-size: 16px; color: #ffffff; text-decoration: none; font-weight: 600;">Reset Password</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Additional Info -->
                            <p style="font-size: 16px; line-height: 1.6; margin-bottom: 24px;">If you did not request this, please ignore this email. Your password will remain unchanged.</p>

                            <p style="font-size: 14px; color: #6b7280; margin-top: 24px;">For security reasons, this link will expire in 24 hours. If you need assistance, please contact our support team.</p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" bgcolor="#f9fafb" style="padding: 24px; color: #6b7280; font-size: 14px; border-top: 1px solid #e5e7eb; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                            <p style="margin: 0 0 16px 0;">&copy; 2025 NPG Solutions. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
