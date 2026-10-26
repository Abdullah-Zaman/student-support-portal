{{-- Layout for emails sent from a staff member's own Gmail. Inline styles only: no CSS inliner runs on these. --}}
@props(['logo', 'title'])
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F6F4F5;font-family:'Plus Jakarta Sans',Arial,Helvetica,sans-serif;color:#3B3339;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#F6F4F5;">
<tr>
<td align="center" style="padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px;">
<tr>
<td align="center" style="background-color:#4A2C42;padding:28px 0;">
<img src="{{ $logo }}" alt="Superior University" height="40" style="height:40px;width:auto;border:0;">
</td>
</tr>
<tr>
<td style="padding:24px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#FFFFFF;border:1px solid #E7E2E5;border-radius:14px;">
<tr>
<td style="padding:28px 28px 24px;font-size:15px;line-height:1.6;">
{{ $slot }}
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="padding:0 16px 28px;font-size:12px;color:#8A808A;">
© {{ date('Y') }} {{ __('SE Department, Superior University') }}
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
