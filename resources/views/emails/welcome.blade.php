<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to YossyVogue</title>
    <style>
        body, table, td, a { text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f8f8f8; font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
    </style>
</head>
<body style="background-color: #f8f8f8; margin: 0; padding: 20px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f8f8;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08); overflow: hidden;">
                    
                    <!-- Header Section -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f53003 0%, #e63000 100%); padding: 40px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">
                                YossyVogue
                            </h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: rgba(255, 255, 255, 0.9);">
                                Your Ultimate Fashion Destination
                            </p>
                        </td>
                    </tr>

                    <!-- Welcome Message -->
                    <tr>
                        <td style="padding: 40px 32px; text-align: center;">
                            <h2 style="margin: 0 0 16px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
                                Welcome, {{ $userName }}!
                            </h2>
                            
                            <p style="margin: 0 0 24px 0; font-size: 16px; line-height: 24px; color: #666666;">
                                We're thrilled to have you join the YossyVogue community! Get ready to explore the latest fashion trends, exclusive collections, and premium products.
                            </p>

                            <!-- Account Info Box -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px 16px;">
                                        <p style="margin: 0 0 12px 0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; font-weight: 600;">Account Details</p>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="padding: 8px 0; text-align: left;">
                                                    <span style="font-size: 14px; color: #6b7280; font-weight: 500;">Name:</span>
                                                </td>
                                                <td style="padding: 8px 0; text-align: right;">
                                                    <span style="font-size: 14px; color: #1f2937; font-weight: 600;">{{ $userName }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; text-align: left; border-top: 1px solid #e5e7eb;">
                                                    <span style="font-size: 14px; color: #6b7280; font-weight: 500;">Email:</span>
                                                </td>
                                                <td style="padding: 8px 0; text-align: right; border-top: 1px solid #e5e7eb;">
                                                    <span style="font-size: 14px; color: #1f2937; font-weight: 600;">{{ $userEmail }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Benefits -->
                            <p style="margin: 0 0 16px 0; font-size: 14px; color: #4b5563; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">What You Can Enjoy:</p>
                            
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                        <p style="margin: 0; font-size: 15px; color: #333333;">✨ Exclusive access to premium fashion collections</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                        <p style="margin: 0; font-size: 15px; color: #333333;">🎁 Special discounts and promotions for members</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                                        <p style="margin: 0; font-size: 15px; color: #333333;">📦 Fast & reliable delivery to your doorstep</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <p style="margin: 0; font-size: 15px; color: #333333;">💬 24/7 customer support for your convenience</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 32px 0 0 0; font-size: 15px; line-height: 24px; color: #666666;">
                                Start shopping now and discover amazing fashion pieces curated just for you. If you have any questions, our team is here to help!
                            </p>
                        </td>
                    </tr>

                    <!-- CTA Button -->
                    <tr>
                        <td style="padding: 0 32px 32px 32px; text-align: center;">
                            <table border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background: linear-gradient(135deg, #f53003 0%, #e63000 100%); border-radius: 6px; padding: 0;">
                                        <a href="{{ config('app.url') }}" style="display: inline-block; padding: 14px 40px; text-decoration: none; color: #ffffff; font-weight: 600; font-size: 16px; border-radius: 6px;">
                                            Start Shopping
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 32px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 12px 0; font-size: 13px; color: #6b7280;">
                                &copy; {{ date('Y') }} YossyVogue. All rights reserved.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                Questions? <a href="mailto:support@yossyvogue.com" style="color: #f53003; text-decoration: none; font-weight: 600;">Contact our support team</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
