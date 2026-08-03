import { FastifyPluginAsync, FastifyRequest, FastifyReply } from 'fastify';
import bcrypt from 'bcryptjs';
import { v4 as uuidv4 } from 'uuid';
import { query, transaction } from '../db';

export const authRoutes: FastifyPluginAsync = async (fastify) => {
  // ── REGISTER ──────────────────────────────────────────────
  fastify.post('/register', async (req: FastifyRequest, reply: FastifyReply) => {
    try {
      const { mobile_number, email, full_name, date_of_birth, password, language, city } = req.body as any;

      // Check if registration is open
      const settings = await query('SELECT registration_open FROM platform_settings LIMIT 1');
      if (!settings.rows[0]?.registration_open) {
        return reply.status(403).send({
          error: language === 'hi'
            ? 'रजिस्ट्रेशन अभी बंद है। बाद में प्रयास करें।'
            : 'Registration is currently closed. Please try again later.',
        });
      }

      // Validate inputs
      if (!mobile_number || !full_name || !date_of_birth || !password || !city) {
        return reply.status(400).send({ error: 'All fields including city are required' });
      }

      // Check mobile uniqueness
      const existing = await query('SELECT id FROM users WHERE mobile_number = $1', [mobile_number]);
      if (existing.rows.length > 0) {
        return reply.status(409).send({ error: 'Mobile number already registered' });
      }

      // Hash password
      const password_hash = await bcrypt.hash(password, 12);

      // Insert user
      const result = await query(
        `INSERT INTO users (mobile_number, email, full_name, date_of_birth, password_hash, language, city)
         VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id, full_name, mobile_number, role, language, city`,
        [mobile_number, email || null, full_name, date_of_birth, password_hash, language || 'en', city]
      );

      const user = result.rows[0];
      const token = fastify.jwt.sign({ id: user.id, role: user.role }, { expiresIn: '7d' });

      return reply.status(201).send({ token, user });
    } catch (err: any) {
      console.error('Register error:', err);
      return reply.status(500).send({ error: `Registration failed: ${err.message || 'Internal server error'}` });
    }
  });

  // ── LOGIN ──────────────────────────────────────────────────
  fastify.post('/login', async (req: FastifyRequest, reply: FastifyReply) => {
    try {
      const { mobile_number, password } = req.body as any;

      const result = await query(
        'SELECT id, full_name, password_hash, role, status, language, wallet_balance FROM users WHERE mobile_number = $1',
        [mobile_number]
      );

      if (result.rows.length === 0) {
        return reply.status(401).send({ error: 'Invalid credentials' });
      }

      const user = result.rows[0];

      if (user.status === 'banned') {
        return reply.status(403).send({ error: 'Account banned. Contact support.' });
      }
      if (user.status === 'suspended') {
        return reply.status(403).send({ error: 'Account temporarily suspended.' });
      }

      const valid = await bcrypt.compare(password, user.password_hash);
      if (!valid) {
        return reply.status(401).send({ error: 'Invalid credentials' });
      }

      // Update last login
      await query('UPDATE users SET last_login = NOW() WHERE id = $1', [user.id]);

      const token = fastify.jwt.sign({ id: user.id, role: user.role }, { expiresIn: '7d' });

      const { password_hash, ...safeUser } = user;
      return reply.send({ token, user: safeUser });
    } catch (err: any) {
      console.error('Login error:', err);
      return reply.status(500).send({ error: `Login failed: ${err.message || 'Internal server error'}` });
    }
  });

  // ── FORGOT PASSWORD (DOB verification) ─────────────────────
  fastify.post('/forgot-password', async (req: FastifyRequest, reply: FastifyReply) => {
    const { mobile_number, date_of_birth, new_password } = req.body as any;

    const result = await query(
      `SELECT id, date_of_birth, failed_dob_attempts, dob_lockout_until
       FROM users WHERE mobile_number = $1`,
      [mobile_number]
    );

    if (result.rows.length === 0) {
      return reply.status(404).send({ error: 'Mobile number not found' });
    }

    const user = result.rows[0];

    // Check lockout
    if (user.dob_lockout_until && new Date() < new Date(user.dob_lockout_until)) {
      return reply.status(429).send({
        error: 'Too many failed attempts. Try again after 24 hours.',
      });
    }

    // Check DOB — use timezone-safe comparison (PG DATE → JS Date shifts timezone)
    const formatDOB = (d: any) => {
      const dt = new Date(d);
      return `${dt.getUTCFullYear()}-${String(dt.getUTCMonth() + 1).padStart(2, '0')}-${String(dt.getUTCDate()).padStart(2, '0')}`;
    };
    // For submitted DOB, parse as local date string (YYYY-MM-DD)
    const submittedDOB = date_of_birth.split('T')[0]; // Already in YYYY-MM-DD format
    const storedDOB = formatDOB(user.date_of_birth);

    if (submittedDOB !== storedDOB) {
      const attempts = user.failed_dob_attempts + 1;
      if (attempts >= 3) {
        // Lock for 24 hours
        await query(
          `UPDATE users SET failed_dob_attempts = $1, dob_lockout_until = NOW() + INTERVAL '24 hours'
           WHERE id = $2`,
          [attempts, user.id]
        );
        return reply.status(429).send({ error: 'Too many failed attempts. Account locked for 24 hours.' });
      }
      await query('UPDATE users SET failed_dob_attempts = $1 WHERE id = $2', [attempts, user.id]);
      return reply.status(400).send({ error: `Incorrect date of birth. ${3 - attempts} attempts remaining.` });
    }

    // DOB correct — update password
    const password_hash = await bcrypt.hash(new_password, 12);
    await query(
      'UPDATE users SET password_hash = $1, failed_dob_attempts = 0, dob_lockout_until = NULL WHERE id = $2',
      [password_hash, user.id]
    );

    return reply.send({ message: 'Password updated successfully' });
  });

  // ── GET PROFILE ─────────────────────────────────────────────
  fastify.get('/me', {
    preHandler: [async (req: FastifyRequest, reply: FastifyReply) => {
      try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: 'Unauthorized' }); }
    }]
  }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id } = req.user as any;
    const result = await query(
      `SELECT id, mobile_number, email, full_name, upi_id, upi_app, language,
              role, status, wallet_balance, total_trades, reputation_score, strike_count,
              is_verified, created_at, last_login
       FROM users WHERE id = $1`,
      [id]
    );
    if (result.rows.length === 0) return reply.status(404).send({ error: 'User not found' });
    return reply.send(result.rows[0]);
  });

  // ── UPDATE UPI PROFILE ──────────────────────────────────────
  fastify.put('/upi-profile', {
    preHandler: [async (req: FastifyRequest, reply: FastifyReply) => {
      try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: 'Unauthorized' }); }
    }]
  }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id } = req.user as any;
    const { upi_id, upi_app } = req.body as any;

    if (!upi_id || !upi_id.includes('@')) {
      return reply.status(400).send({ error: 'Invalid UPI ID format' });
    }

    await query(
      'UPDATE users SET upi_id = $1, upi_app = $2 WHERE id = $3',
      [upi_id, upi_app, id]
    );

    return reply.send({ message: 'UPI profile updated' });
  });

  // ── UPDATE LANGUAGE ─────────────────────────────────────────
  fastify.put('/language', {
    preHandler: [async (req: FastifyRequest, reply: FastifyReply) => {
      try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: 'Unauthorized' }); }
    }]
  }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id } = req.user as any;
    const { language } = req.body as any;

    if (!['en', 'hi'].includes(language)) {
      return reply.status(400).send({ error: 'Invalid language. Use en or hi.' });
    }

    await query('UPDATE users SET language = $1 WHERE id = $2', [language, id]);
    return reply.send({ message: 'Language updated' });
  });
};
