const startBtn = document.getElementById("startBtn");
const stopBtn = document.getElementById("stopBtn");
const voiceText = document.getElementById("voiceText");
const statusIndicator = document.getElementById("statusIndicator");

let recognition;
let isListening = false;

if ('webkitSpeechRecognition' in window) {
    recognition = new webkitSpeechRecognition();
    recognition.continuous = true; // ✅ Keeps listening until you stop it
    recognition.lang = 'en-US';    // ✅ You can change this to your preferred language
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;

    recognition.onstart = function () {
        isListening = true;
        statusIndicator.innerText = "🎙️ Listening...";
        startBtn.disabled = true;
        stopBtn.disabled = false;
    };

    recognition.onresult = function (event) {
        let transcript = '';
        for (let i = event.resultIndex; i < event.results.length; ++i) {
            transcript += event.results[i][0].transcript;
        }
        voiceText.innerText = transcript.trim();
    };

    recognition.onerror = function (event) {
        statusIndicator.innerText = `❌ Error: ${event.error}`;
    };

    recognition.onend = function () {
        if (isListening) {
            // Automatically restart if still listening
            recognition.start();
        } else {
            statusIndicator.innerText = "🛑 Mic stopped";
            startBtn.disabled = false;
            stopBtn.disabled = true;
        }
    };
} else {
    alert("Speech Recognition is not supported in this browser. Please use Google Chrome.");
}

function startListening() {
    if (recognition && !isListening) {
        isListening = true;
        recognition.start();
    }
}

function stopListening() {
    if (recognition && isListening) {
        isListening = false;
        recognition.stop();
    }
}



function sendWhatsApp() {
    const number = document.getElementById("phoneNumber").value.trim();
    const message = encodeURIComponent(voiceText.innerText.trim());
    if (!number || !message) {
        alert("Please provide both a phone number and a message.");
        return;
    }
    const link = `https://wa.me/${91}${number}?text=${message}`;
    window.open(link, "_blank");
}
