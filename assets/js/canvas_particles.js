/**
 * Sudarshan Yuvak Mandal - Festive Canvas Animations
 * Renders floating marigold petals and glowing light particles on canvas.
 */
(function() {
    'use strict';

    const canvas = document.getElementById('festiveCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];
    const particleCount = 35;

    function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    }

    class Particle {
        constructor() {
            this.reset();
        }

        reset() {
            this.x = Math.random() * width;
            this.y = Math.random() * height;
            this.radius = Math.random() * 4 + 2;
            this.speedY = Math.random() * 0.8 + 0.3;
            this.speedX = Math.sin(Math.random() * Math.PI) * 0.5;
            this.opacity = Math.random() * 0.5 + 0.2;
            // Palette: Warm Saffron (#FF9933), Marigold Gold (#FFB700), Soft Amber
            const colors = ['#FF9933', '#FFB700', '#FFE082', '#DA4D12'];
            this.color = colors[Math.floor(Math.random() * colors.length)];
            this.rotation = Math.random() * Math.PI * 2;
            this.rotSpeed = (Math.random() - 0.5) * 0.02;
        }

        update() {
            this.y -= this.speedY;
            this.x += Math.sin(this.y * 0.01) * 0.5 + this.speedX;
            this.rotation += this.rotSpeed;

            if (this.y < -20 || this.x < -20 || this.x > width + 20) {
                this.y = height + 20;
                this.x = Math.random() * width;
            }
        }

        draw() {
            ctx.save();
            ctx.translate(this.x, this.y);
            ctx.rotate(this.rotation);
            ctx.globalAlpha = this.opacity;
            ctx.fillStyle = this.color;

            // Draw petal/glowing orb
            ctx.beginPath();
            ctx.ellipse(0, 0, this.radius, this.radius * 1.5, 0, 0, Math.PI * 2);
            ctx.fill();

            // Subtle glow
            ctx.shadowBlur = 8;
            ctx.shadowColor = this.color;

            ctx.restore();
        }
    }

    function init() {
        resize();
        window.addEventListener('resize', resize);
        particles = [];
        for (let i = 0; i < particleCount; i++) {
            particles.push(new Particle());
        }
        animate();
    }

    function animate() {
        ctx.clearRect(0, 0, width, height);
        particles.forEach(p => {
            p.update();
            p.draw();
        });
        requestAnimationFrame(animate);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
