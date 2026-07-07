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

- PHP (v8 or higher)
- PostgreSQL (for database)

## Quick Start

### Backend Setup

1. Navigate to the backend directory:
```bash
cd backend

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

The API server will run on `http://localhost/`

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


## Technologies Used

### Backend
- **PostgreSQL** - Database
- **Mongoose** - ODM for MongoDB
- **CORS** - Cross-origin resource sharing
- **Dotenv** - Environment variables

- **CSS** - Styling

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `http://www.innerzig.com/` | 

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


## Support

For issues or questions, please create an issue or contact the development team.

skumar140977@gmail.com 
Whatsapp: +91-9900145576

## License

ISC

