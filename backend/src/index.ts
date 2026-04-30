import express from 'express';
import cors from 'cors';
import { initializeDb } from './db/database';
import routes from './routes';

const app = express();
const PORT = process.env.PORT || 3001;

app.use(cors({ origin: process.env.FRONTEND_URL || 'http://localhost:5173', credentials: true }));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Health check
app.get('/health', (_req, res) => res.json({ status: 'ok', timestamp: new Date().toISOString() }));

app.use('/api', routes);

// Initialize DB and start server
initializeDb();
app.listen(PORT, () => {
  console.log(`Tableau de Bord Fiscal - Backend running on http://localhost:${PORT}`);
});

export default app;
