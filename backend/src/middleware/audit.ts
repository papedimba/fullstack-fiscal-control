import { Response, NextFunction } from 'express';
import { AuthRequest } from './auth';
import { getDb } from '../db/database';

export function logAudit(action: string, tableName: string, recordId?: number, details?: string) {
  return (req: AuthRequest, _res: Response, next: NextFunction): void => {
    const db = getDb();
    try {
      db.prepare(
        'INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?,?,?,?,?)'
      ).run(req.user?.userId ?? null, action, tableName, recordId ?? null, details ?? '');
    } catch {
      // Non-blocking
    }
    next();
  };
}

export function auditAfter(userId: number, action: string, tableName: string, recordId?: number, details?: string): void {
  const db = getDb();
  try {
    db.prepare(
      'INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?,?,?,?,?)'
    ).run(userId, action, tableName, recordId ?? null, details ?? '');
  } catch {
    // Non-blocking
  }
}
