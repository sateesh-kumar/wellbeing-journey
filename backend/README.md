# Backend - Happiness Audit Project

Node.js Express server for the Happiness Audit application.

## Setup

1. Install dependencies:
```bash
npm install
```

2. Create `.env` file from `.env.example`:
```bash
cp .env.example .env
```

3. Update `.env` with your configuration

## Development

Run in development mode with auto-reload:
```bash
npm run dev
```

## Production

Start the server:
```bash
npm start
```

## Project Structure

- `src/index.js` - Application entry point
- `src/routes/` - API route definitions
- `src/controllers/` - Route logic and business logic
- `src/models/` - Database models
- `src/middleware/` - Custom middleware functions

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | / | Welcome message |

