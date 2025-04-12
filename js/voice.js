let recognition;
let isListening = false;
let finalTranscript = '';

// Initialize speech recognition
function initializeSpeechRecognition() {
    if ('webkitSpeechRecognition' in window) {
        recognition = new webkitSpeechRecognition();
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        recognition.onstart = function() {
            isListening = true;
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('startBtn').classList.add('recording');
            updateStatus('Listening...');
        };

        recognition.onend = function() {
            isListening = false;
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('startBtn').classList.remove('recording');
            updateStatus('Ready');
        };

        recognition.onresult = function(event) {
            let interimTranscript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interimTranscript += transcript;
                }
            }

            // Display both final and interim results
            document.getElementById('voiceText').innerHTML = finalTranscript + 
                '<span style="color: #666;">' + interimTranscript + '</span>';
        };

        recognition.onerror = function(event) {
            console.error('Speech recognition error:', event.error);
            updateStatus('Error: ' + event.error);
            stopListening();
        };
    } else {
        updateStatus('Speech recognition not supported in this browser');
    }
}

// Start listening
function startListening() {
    if (!recognition) {
        initializeSpeechRecognition();
    }
    
    if (!isListening) {
        finalTranscript = '';
        recognition.start();
    }
}

// Stop listening
function stopListening() {
    if (recognition && isListening) {
        recognition.stop();
    }
}

// Edit text
function editText() {
    const voiceText = document.getElementById('voiceText');
    const currentText = voiceText.textContent;
    
    // Create a textarea for editing
    const textarea = document.createElement('textarea');
    textarea.value = currentText;
    textarea.style.width = '100%';
    textarea.style.minHeight = '150px';
    textarea.style.padding = '10px';
    textarea.style.border = '1px solid #ddd';
    textarea.style.borderRadius = '5px';
    
    // Replace paragraph with textarea
    voiceText.parentNode.replaceChild(textarea, voiceText);
    
    // Add save button
    const saveButton = document.createElement('button');
    saveButton.className = 'btn btn-primary mt-2';
    saveButton.innerHTML = '<i class="fas fa-save"></i> Save';
    saveButton.onclick = function() {
        const editedText = textarea.value;
        // Create new paragraph with edited text
        const newParagraph = document.createElement('p');
        newParagraph.id = 'voiceText';
        newParagraph.textContent = editedText;
        
        // Replace textarea with new paragraph
        textarea.parentNode.replaceChild(newParagraph, textarea);
        saveButton.remove();
        
        // Correct grammar
        correctGrammar(editedText);
    };
    
    textarea.parentNode.appendChild(saveButton);
}

// Correct grammar
function correctGrammar(text) {
    // This is a simple implementation. For better results, you might want to use a grammar correction API
    const corrections = {
        'i ': 'I ',
        'i\'m': 'I\'m',
        'i\'ve': 'I\'ve',
        'i\'ll': 'I\'ll',
        'i\'d': 'I\'d',
        ' dont ': ' don\'t ',
        ' doesnt ': ' doesn\'t ',
        ' wont ': ' won\'t ',
        ' cant ': ' can\'t ',
        ' couldnt ': ' couldn\'t ',
        ' wouldnt ': ' wouldn\'t ',
        ' shouldnt ': ' shouldn\'t ',
        ' isnt ': ' isn\'t ',
        ' arent ': ' aren\'t ',
        ' wasnt ': ' wasn\'t ',
        ' werent ': ' weren\'t ',
        ' havent ': ' haven\'t ',
        ' hasnt ': ' hasn\'t ',
        ' hadnt ': ' hadn\'t ',
        ' wont ': ' won\'t ',
        ' wouldnt ': ' wouldn\'t ',
        ' shouldnt ': ' shouldn\'t ',
        ' couldnt ': ' couldn\'t ',
        ' mightnt ': ' mightn\'t ',
        ' mustnt ': ' mustn\'t '
    };

    let correctedText = text;
    for (const [wrong, right] of Object.entries(corrections)) {
        correctedText = correctedText.replace(new RegExp(wrong, 'gi'), right);
    }

    // Update the text with corrections
    document.getElementById('voiceText').textContent = correctedText;
}

// Update status indicator
function updateStatus(message) {
    document.getElementById('statusIndicator').textContent = message;
}

// Send WhatsApp message
function sendWhatsApp() {
    const phoneNumber = document.getElementById('phoneNumber').value;
    const message = document.getElementById('voiceText').textContent;
    
    if (!phoneNumber) {
        updateStatus('Please enter a phone number');
        return;
    }
    
    if (!message || message === 'Your message will appear here...') {
        updateStatus('Please record a message first');
        return;
    }
    
    // Format phone number (remove any non-digit characters)
    const formattedNumber = phoneNumber.replace(/\D/g, '');
    
    // Create WhatsApp URL
    const whatsappUrl = `https://wa.me/${formattedNumber}?text=${encodeURIComponent(message)}`;
    
    // Open WhatsApp in a new tab
    window.open(whatsappUrl, '_blank');
    updateStatus('Opening WhatsApp...');
}

// Initialize when the page loads
document.addEventListener('DOMContentLoaded', function() {
    updateStatus('Ready');
});
  