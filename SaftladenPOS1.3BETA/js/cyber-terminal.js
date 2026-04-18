class CyberTerminal {
    constructor() {
        this.matrixChars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン';
        this.init();
    }

    init() {
        this.createMatrixRain();
        this.addTerminalInteractivity();
        this.startSystemAnimations();
        this.typeExistingContent();
    }

    async typeExistingContent() {
        const terminalContent = document.getElementById('terminal-content');
        if (!terminalContent) return;

        const lines = Array.from(terminalContent.children);

        // Hide all initially
        lines.forEach(line => line.style.opacity = '0');

        for (const line of lines) {
            line.style.opacity = '1';
            if (line.classList.contains('terminal-line') || line.classList.contains('terminal-line1')) {
                const command = line.querySelector('.command');
                if (command) {
                    const text = command.textContent;
                    command.textContent = '';
                    await this.typeText(command, text, 30);
                }
            } else {
                // For outputs, maybe a slight delay
                line.style.transition = 'opacity 0.4s ease-in';
                line.style.opacity = '1';
                await new Promise(r => setTimeout(r, 100));
            }

            terminalContent.scrollTop = terminalContent.scrollHeight;
        }
    }

    typeText(element, text, speed) {
        return new Promise(resolve => {
            let i = 0;
            const timer = setInterval(() => {
                element.textContent += text[i];
                i++;
                if (i >= text.length) {
                    clearInterval(timer);
                    resolve();
                }
            }, speed);
        });
    }

    createMatrixRain() {
        const matrixDisplay = document.getElementById('matrix-display');
        if (!matrixDisplay) return;

        setInterval(() => {
            this.addMatrixColumn(matrixDisplay);
        }, 150);
    }

    addMatrixColumn(container) {
        const terminal = container.closest('.terminal-container');
        const isRed = terminal && terminal.classList.contains('red-alert');
        const mainColor = isRed ? '#ff3b30' : '#00ff41';
        const shadowColor = isRed ? 'rgba(255, 59, 48, 0.8)' : 'rgba(0, 255, 65, 0.8)';

        const column = document.createElement('div');
        column.style.cssText = `
            position: absolute;
            left: ${Math.random() * 100}%;
            top: -20px;
            color: ${mainColor};
            font-size: 12px;
            line-height: 14px;
            animation: matrixRain 3s linear forwards;
            opacity: 0.7;
            text-shadow: 0 0 5px ${shadowColor};
        `;


        let matrixString = '';
        for (let i = 0; i < 8; i++) {
            matrixString += this.matrixChars[Math.floor(Math.random() * this.matrixChars.length)] + '<br>';
        }
        column.innerHTML = matrixString;

        container.appendChild(column);

        setTimeout(() => {
            if (column.parentNode) {
                column.parentNode.removeChild(column);
            }
        }, 3000);
    }

    addTerminalInteractivity() {
        const terminal = document.querySelector('.terminal-container');
        if (!terminal) return;

        // Using delegation to handle dynamic results
        document.body.addEventListener('click', (e) => {
            if (e.target.closest('.terminal-container')) {
                this.createRipple(e);
            }
        });

        document.body.addEventListener('click', (e) => {
            const control = e.target.closest('.control');
            if (control) {
                const controls = Array.from(control.parentNode.children);
                const index = controls.indexOf(control);
                this.handleControlClick(control, index);
            }
        });
    }

    createRipple(e) {
        const terminal = e.target.closest('.terminal-container');
        const rect = terminal.getBoundingClientRect();
        const ripple = document.createElement('div');

        const size = 100;
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            background: radial-gradient(circle, rgba(0, 255, 65, 0.4) 0%, transparent 70%);
            border-radius: 50%;
            left: ${x}px;
            top: ${y}px;
            pointer-events: none;
            animation: rippleExpand 0.6s ease-out forwards;
            z-index: 10;
        `;

        terminal.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    }

    handleControlClick(control, index) {
        const terminal = control.closest('.terminal-container');

        switch(index) {
            case 0: // Close
                this.triggerScreenGlitch(terminal);
                break;
            case 1: // Minimize
                terminal.style.transform = 'scaleY(0.1)';
                setTimeout(() => {
                    terminal.style.transform = 'scaleY(1)';
                }, 800);
                break;
            case 2: // Maximize
                terminal.classList.toggle('maximized');
                break;
        }
    }

    startSystemAnimations() {
        setInterval(() => {
            const statusIndicator = document.querySelector('.status-indicator');
            if (statusIndicator) {
                statusIndicator.style.transform = 'scale(1.5)';
                setTimeout(() => {
                    statusIndicator.style.transform = 'scale(1)';
                }, 200);
            }
        }, 2000);
    }

    triggerScreenGlitch(terminal) {
        if (!terminal) return;

        terminal.style.animation = 'glitchEffect 0.3s ease-in-out';
        setTimeout(() => {
            terminal.style.filter = 'invert(1) hue-rotate(180deg)';
            setTimeout(() => {
                terminal.style.filter = 'none';
                terminal.style.animation = '';
            }, 100);
        }, 150);
    }
}

// Don't auto-instantiate if we want to control it from index.html
// But the original had it. I'll keep it for simple pages,
// and in handleSearch I'll just call the method if global instance exists.
window.cyberTerminalInstance = new CyberTerminal();
