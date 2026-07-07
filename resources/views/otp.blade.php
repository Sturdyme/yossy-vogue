<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Your Verification Code</title>
    <style>
        /* Basic resets for email clients */
        body, table, td, a { text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f6f9fc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body>

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f6f9fc; padding: 40px 0;">
        <tr>
            <td align="center">
                
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 500px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); overflow: hidden;">
                    
                    <tr>
                        <td align="center" style="padding: 32px 32px 20px 32px; border-bottom: 1px solid #f0f0f0;">
                            <span style="font-size: 20px; font-weight: 700; color: #333333; letter-spacing: -0.5px;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px 32px; text-align: center;">
                            <h2 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 600; color: #1a1a1a;">
                                Your OTP Code
                            </h2>
                            
                            <p style="margin: 0 0 32px 0; font-size: 16px; line-height: 24px; color: #666666;">
                                Your verification code is below. Enter this code on the verification screen to continue.
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <div style="display: inline-block; background-color: #f4f7fa; border: 1px solid #e1e8ed; border-radius: 6px; padding: 16px 32px; min-width: 180px;">
                                            <h1 style="margin: 0; font-size: 36px; font-weight: 700; color: #2563eb; letter-spacing: 6px; padding-left: 6px;">
                                                {{ $otp }}
                                            </h1>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 32px 0 0 0; font-size: 14px; line-height: 20px; color: #999999; font-style: italic;">
                                This code expires in 10 minutes. If you did not request this, please ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 32px 32px 32px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; line-height: 18px; color: #b0b7bd;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>