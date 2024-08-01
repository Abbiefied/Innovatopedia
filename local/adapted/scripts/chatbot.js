document.addEventListener('DOMContentLoaded', function() {
    const chatbotIcon = document.getElementById('chatbot-icon');
    const chatbotInterface = document.getElementById('chatbot-interface');
    const chatInput = document.getElementById('chat-input');
    const sendButton = document.getElementById('send-button');
    const chatContent = document.getElementById('chat-content');
    const closeChat = document.getElementById('close-chat');

    let username = '';

    if (chatbotIcon) {
        chatbotIcon.addEventListener('click', function() {
            chatbotInterface.style.display = chatbotInterface.style.display === 'none' ? 'block' : 'none';
            if (chatContent.children.length === 0) {
                fetchUsername().then(() => {
                    addMessage('Bot', `Hello ${username}, how may I assist you today?`);
                });
            }
        });
    }

    if (closeChat) {
        closeChat.addEventListener('click', function() {
            chatbotInterface.style.display = 'none';
        });
    }

    sendButton.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function sendMessage() {
        const message = chatInput.value;
        if (message.trim() !== '') {
            addMessage('You', message);
            chatInput.value = '';
            fetchResponse(message);
        }
    }

    function addMessage(sender, message) {
        const messageElement = document.createElement('div');
        messageElement.className = sender === 'You' ? 'user-message' : 'bot-message';
        messageElement.textContent = `${sender}: ${message}`;
        chatContent.appendChild(messageElement);
        chatContent.scrollTop = chatContent.scrollHeight;
    }

    async function fetchResponse(message) {
        try {
            const response = await fetch(`${M.cfg.wwwroot}/local/adapted/chatbot.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ message }),
                credentials: 'same-origin'
            });
    
            const data = await response.json();
            addMessage('Bot', data.response);
        } catch (error) {
            addMessage('Bot', 'Error fetching response');
        }
    }

    async function fetchUsername() {
        try {
            const response = await fetch(`${M.cfg.wwwroot}/local/adapted/get_username.php`, {
                method: 'GET',
                credentials: 'same-origin'
            });
            const data = await response.json();
            username = data.username;
        } catch (error) {
            console.error('Error fetching username:', error);
            username = 'User';
        }
    }
});