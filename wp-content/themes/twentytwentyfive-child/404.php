<?php
$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);

switch ($lang) {
    case 'uk':
        $title = 'Ой!';
        $message = 'Тут для вас нічого немає.';
        break;
    case 'tr':
        $title = 'Oops!';
        $message = 'Burada sana göre bir şey yok.';
        break;
    default:
        $title = 'Oops!';
        $message = 'There’s nothing for you here.';
}
status_header(403);
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo esc_html($title); ?></title>
  <style>
    body {
      background-color: #0f172a;
      color: #f1f5f9;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
      flex-direction: column;
      text-align: center;
    }
    h1 {
      font-size: 3rem;
      margin-bottom: 1rem;
    }
    p {
      font-size: 1.25rem;
      color: #94a3b8;
    }
  </style>
</head>
<body>
  <h1><?php echo esc_html($title); ?> 👀</h1>
  <p><?php echo esc_html($message); ?></p>
</body>
</html>
