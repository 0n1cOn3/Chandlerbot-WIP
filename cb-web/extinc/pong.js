// Constants
const DIRECTION = {
    IDLE: 0,
    UP: 1,
    DOWN: 2,
    LEFT: 3,
    RIGHT: 4
};

const rounds = [5, 5, 3, 3, 2];
const colors = ['#1abc9c', '#2ecc71', '#3498db', '#e74c3c', '#9b59b6'];

// Ball Object
class Ball {
    constructor(incrementedSpeed = 9) {
        this.width = 18;
        this.height = 18;
        this.x = Pong.canvas.width / 2 - 9;
        this.y = Pong.canvas.height / 2 - 9;
        this.moveX = DIRECTION.IDLE;
        this.moveY = DIRECTION.IDLE;
        this.speed = incrementedSpeed;
    }
}

// Paddle Object
class Paddle {
    constructor(side) {
        this.width = 18;
        this.height = 70;
        this.x = side === 'left' ? 150 : Pong.canvas.width - 150;
        this.y = Pong.canvas.height / 2 - 35;
        this.score = 0;
        this.move = DIRECTION.IDLE;
        this.speed = 10;
    }
}

// Game Object
class Game {
    constructor() {
        this.canvas = document.querySelector('canvas');
        this.context = this.canvas.getContext('2d');
        this.canvas.width = 1400;
        this.canvas.height = 1000;
        this.canvas.style.width = `${this.canvas.width / 2}px`;
        this.canvas.style.height = `${this.canvas.height / 2}px`;
        this.player = new Paddle('left');
        this.paddle = new Paddle('right');
        this.ball = new Ball();
        this.paddle.speed = 8;
        this.running = this.over = false;
        this.turn = this.paddle;
        this.timer = this.round = 0;
        this.color = '#2c3e50';
    }

    initialize() {
        Pong.menu();
        Pong.listen();
    }

    endGameMenu(text) {
        this.context.font = '50px Courier New';
        this.context.fillStyle = this.color;
        this.context.fillRect(Pong.canvas.width / 2 - 350, Pong.canvas.height / 2 - 48, 700, 100);
        this.context.fillStyle = '#ffffff';
        this.context.fillText(text, Pong.canvas.width / 2, Pong.canvas.height / 2 + 15);
        
        setTimeout(() => {
            Object.assign(Pong, new Game());
            Pong.initialize();
        }, 3000);
    }

    menu() {
        this.draw();
        this.context.font = '50px Courier New';
        this.context.fillStyle = this.color;
        this.context.fillRect(Pong.canvas.width / 2 - 350, Pong.canvas.height / 2 - 48, 700, 100);
        this.context.fillStyle = '#ffffff';
        this.context.fillText('Press any key to begin', Pong.canvas.width / 2, Pong.canvas.height / 2 + 15);
    }

    update() {
        if (!this.over) {
            this.handleBallBounds();
            this.handlePlayerMovement();
            this.handleTurn();
            this.handlePaddleMovement();
            this.handleCollisions();
        }
        this.handleRoundTransition();
    }

    handleBallBounds() {
        if (this.ball.x <= 0) this.resetTurn(this.paddle, this.player);
        if (this.ball.x >= this.canvas.width - this.ball.width) this.resetTurn(this.player, this.paddle);
        if (this.ball.y <= 0) this.ball.moveY = DIRECTION.DOWN;
        if (this.ball.y >= this.canvas.height - this.ball.height) this.ball.moveY = DIRECTION.UP;
    }

    handlePlayerMovement() {
        if (this.player.move === DIRECTION.UP) this.player.y -= this.player.speed;
        if (this.player.move === DIRECTION.DOWN) this.player.y += this.player.speed;

        if (this.player.y <= 0) this.player.y = 0;
        if (this.player.y >= this.canvas.height - this.player.height) this.player.y = this.canvas.height - this.player.height;
    }

    handleTurn() {
        if (this._turnDelayIsOver() && this.turn) {
            this.ball.moveX = this.turn === this.player ? DIRECTION.LEFT : DIRECTION.RIGHT;
            this.ball.moveY = [DIRECTION.UP, DIRECTION.DOWN][Math.round(Math.random())];
            this.ball.y = Math.floor(Math.random() * this.canvas.height - 200) + 200;
            this.turn = null;
        }
    }

    handlePaddleMovement() {
        if (this.paddle.y > this.ball.y - (this.paddle.height / 2)) {
            this.paddle.y -= (this.ball.moveX === DIRECTION.RIGHT ? this.paddle.speed / 1.5 : this.paddle.speed / 4);
        }
        if (this.paddle.y < this.ball.y - (this.paddle.height / 2)) {
            this.paddle.y += (this.ball.moveX === DIRECTION.RIGHT ? this.paddle.speed / 1.5 : this.paddle.speed / 4);
        }

        if (this.paddle.y >= this.canvas.height - this.paddle.height) this.paddle.y = this.canvas.height - this.paddle.height;
        if (this.paddle.y <= 0) this.paddle.y = 0;
    }

    handleCollisions() {
        if (this.ball.x - this.ball.width <= this.player.x && this.ball.x >= this.player.x - this.player.width) {
            if (this.ball.y <= this.player.y + this.player.height && this.ball.y + this.ball.height >= this.player.y) {
                this.ball.x = (this.player.x + this.ball.width);
                this.ball.moveX = DIRECTION.RIGHT;
                beep1.play();
            }
        }

        if (this.ball.x - this.ball.width <= this.paddle.x && this.ball.x >= this.paddle.x - this.paddle.width) {
            if (this.ball.y <= this.paddle.y + this.paddle.height && this.ball.y + this.ball.height >= this.paddle.y) {
                this.ball.x = (this.paddle.x - this.ball.width);
                this.ball.moveX = DIRECTION.LEFT;
                beep1.play();
            }
        }
    }

    handleRoundTransition() {
        if (this.player.score === rounds[this.round]) {
            if (!rounds[this.round + 1]) {
                this.over = true;
                setTimeout(() => this.endGameMenu('Winner!'), 1000);
            } else {
                this.resetRound();
                beep3.play();
            }
        } else if (this.paddle.score === rounds[this.round]) {
            this.over = true;
            setTimeout(() => this.endGameMenu('Game Over!'), 1000);
        }
    }

    resetRound() {
        this.color = this._generateRoundColor();
        this.player.score = this.paddle.score = 0;
        this.player.speed += 0.5;
        this.paddle.speed += 1;
        this.ball.speed += 1;
        this.round += 1;
    }

    draw() {
        this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.context.fillStyle = this.color;
        this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);

        this.context.fillStyle = '#ffffff';
        this.context.fillRect(this.player.x, this.player.y, this.player.width, this.player.height);
        this.context.fillRect(this.paddle.x, this.paddle.y, this.paddle.width, this.paddle.height);

        if (this._turnDelayIsOver()) {
            this.context.fillRect(this.ball.x, this.ball.y, this.ball.width, this.ball.height);
        }

        this.drawNet();
        this.drawScore();
    }

    drawNet() {
        this.context.beginPath();
        this.context.setLineDash([7, 15]);
        this.context.moveTo(this.canvas.width / 2, this.canvas.height - 140);
        this.context.lineTo(this.canvas.width / 2, 140);
        this.context.lineWidth = 10;
        this.context.strokeStyle = '#ffffff';
        this.context.stroke();
    }

    drawScore() {
        this.context.font = '100px Courier New';
        this.context.textAlign = 'center';
        this.context.fillText(this.player.score.toString(), this.canvas.width / 2 - 300, 200);
        this.context.fillText(this.paddle.score.toString(), this.canvas.width / 2 + 300, 200);

        this.context.font = '30px Courier New';
        this.context.fillText('Round ' + (this.round + 1), this.canvas.width / 2, 35);
        this.context.font = '40px Courier';
        this.context.fillText(rounds[this.round] || rounds[this.round - 1], this.canvas.width / 2, 100);
    }

    loop() {
        this.update();
        this.draw();

        if (!this.over) requestAnimationFrame(this.loop.bind(this));
    }

    listen() {
        document.addEventListener('keydown', (key) => {
            if (!this.running) {
                this.running = true;
                window.requestAnimationFrame(this.loop.bind(this));
            }

            if (key.keyCode === 38 || key.keyCode === 87) this.player.move = DIRECTION.UP;
            if (key.keyCode === 40 || key.keyCode === 83) this.player.move = DIRECTION.DOWN;
        });

        document.addEventListener('keyup', () => this.player.move = DIRECTION.IDLE);
    }

    resetTurn(victor, loser) {
        this.ball = new Ball(this.ball.speed);
        this.turn = loser;
        this.timer = Date.now();
        victor.score++;
        beep2.play();
    }

    _turnDelayIsOver() {
        return Date.now() - this.timer >= 1000;
    }

    _generateRoundColor() {
        let newColor = colors[Math.floor(Math.random() * colors.length)];
        if (newColor === this.color) return this._generateRoundColor();
        return newColor;
    }
}

const Pong = new Game();
Pong.initialize();
