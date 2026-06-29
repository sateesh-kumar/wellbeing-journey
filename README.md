# Happiness Audit Project

A full-stack MERN (MongoDB, Express, React, Node.js) application for conducting happiness audit surveys.

## Project Structure

```
Happiness_audit_project/
├── backend/                 # Node.js Express API
│   ├── src/
│   │   ├── index.js        # Server entry point
│   │   ├── routes/         # API routes
│   │   ├── controllers/    # Route handlers
│   │   ├── models/         # Database models
│   │   └── middleware/     # Custom middleware
│   ├── package.json
│   ├── .env.example
│   └── README.md
└── frontend/                # React web application
    ├── src/
    │   ├── index.js        # React entry point
    │   ├── App.js          # Main component
    │   ├── pages/          # Page components
    │   ├── components/     # Reusable components
    │   ├── services/       # API services
    │   └── styles/         # CSS files
    ├── public/
    ├── package.json
    ├── .env.example
    └── README.md
```

## Prerequisites

- Node.js (v14 or higher)
- npm or yarn
- MongoDB (for database)

## Quick Start

### Backend Setup

1. Navigate to the backend directory:
```bash
cd backend
```

2. Install dependencies:
```bash
npm install
```

3. Create `.env` file from `.env.example`:
```bash
cp .env.example .env
```

4. Update `.env` with your configuration:
```
PORT=5000
NODE_ENV=development
MONGODB_URI=mongodb://localhost:27017/happiness_audit
JWT_SECRET=your_secret_key
```

5. Start the development server:
```bash
npm run dev
```

The API server will run on `http://localhost:5000`

### Frontend Setup

1. Navigate to the frontend directory:
```bash
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Create `.env` file from `.env.example`:
```bash
cp .env.example .env
```

4. Start the development server:
```bash
npm start
```

The React app will open at `http://localhost:3000`

## Available Scripts

### Backend
- `npm start` - Start the production server
- `npm run dev` - Start development server with auto-reload
- `npm test` - Run tests

### Frontend
- `npm start` - Start development server
- `npm run build` - Create production build
- `npm test` - Run tests

## Technologies Used

### Backend
- **Express.js** - Web framework
- **MongoDB** - Database
- **Mongoose** - ODM for MongoDB
- **CORS** - Cross-origin resource sharing
- **Dotenv** - Environment variables

### Frontend
- **React** - UI library
- **React Router** - Client-side routing
- **Axios** - HTTP client
- **CSS** - Styling

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | API health check |

## Development Workflow

1. Start the backend server: `cd backend && npm run dev`
2. In another terminal, start the frontend: `cd frontend && npm start`
3. Make changes to code - both will auto-reload
4. Test API calls in frontend using the `api.js` service

## Environment Variables

### Backend (.env)
```
PORT=5000
NODE_ENV=development
MONGODB_URI=mongodb://localhost:27017/happiness_audit
JWT_SECRET=your_jwt_secret_key_here
```

### Frontend (.env)
```
REACT_APP_API_URL=http://localhost:5000
```

## Project Checklist

- [ ] Backend API setup complete
- [ ] Frontend React app setup complete
- [ ] Environment files configured
- [ ] Dependencies installed
- [ ] Server running on port 5000
- [ ] Frontend running on port 3000
- [ ] API connectivity tested

## Next Steps

1. Configure MongoDB connection
2. Create database models
3. Implement API routes and controllers
4. Build React components
5. Add authentication (JWT)
6. Implement error handling
7. Add tests
8. Deploy to production

## Support

For issues or questions, please create an issue or contact the development team.

## License

ISC

