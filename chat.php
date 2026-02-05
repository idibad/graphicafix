<?php
require_once('config.php');
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
$query = "SELECT * FROM users WHERE username = '$username'";
$result = mysqli_query($conn, $query);

$data = mysqli_fetch_assoc($result);
$user_id = $data['user_id'];
$phone = $data['phone'];
$email = $data['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="css/style.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet">
    <link href="css/all.min.css" rel="stylesheet">
    <title>Group Chat</title>
    <style>
    body {
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
    background-color: #f7f7f7;
    }
    </style>
</head>
<body>
    <div id="chat-container">
        <div id="messages"></div>
        <form id="message-form">
            <input type="text" id="message-input" placeholder="Type your message here..." autocomplete="off" required>
            <button type="submit" id="send-button"><i class="fa fa-paper-plane"></i></button>
        </form>
        <audio id="notification-sound" src="notification.mp3" preload="auto"></audio>
    </div>
    <script>
        const userId = <?php echo json_encode($user_id); ?>;
        const messagesDiv = document.getElementById('messages');
        const notificationSound = document.getElementById('notification-sound');
        let lastMessageId = 0;

        function linkify(text) {
            const urlPattern = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
            return text.replace(urlPattern, '<a href="$1" target="_blank">$1</a>');
        }

        function fetchMessages() {
            fetch('fetch_messages.php')
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0 && data[data.length - 1].id > lastMessageId) {
                        const latestMessage = data[data.length - 1];
                        if (latestMessage.user_id !== userId) {
                            notificationSound.play();
                        }
                        lastMessageId = latestMessage.id;

                        // Only scroll to bottom if new messages are added
                        const shouldScroll = messagesDiv.scrollTop + messagesDiv.clientHeight === messagesDiv.scrollHeight;

                        messagesDiv.innerHTML = '';
                        data.forEach(msg => {
                            const msgDiv = document.createElement('div');
                            msgDiv.classList.add('message');
                            msgDiv.classList.add(msg.user_id == userId ? 'self' : 'other');
                            msgDiv.innerHTML = `<div class="username">${msg.username}</div><div class="message-content">${linkify(msg.message)}</div>`;
                            messagesDiv.appendChild(msgDiv);
                        });

                        if (shouldScroll) {
                            messagesDiv.scrollTop = messagesDiv.scrollHeight;
                        }
                    }
                });
        }

        document.getElementById('message-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value;

            fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message })
            }).then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    messageInput.value = '';
                    fetchMessages();
                } else {
                    alert(data.message);
                }
            });
        });

        setInterval(fetchMessages, 1000);
        fetchMessages();
    </script>
</body>
</html>