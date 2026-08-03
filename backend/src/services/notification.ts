export async function createNotification(
  client: any,
  data: {
    user_id: string;
    type: string;
    title_en: string;
    title_hi: string;
    body_en: string;
    body_hi: string;
    trade_id?: string;
    dispute_id?: string;
  }
) {
  await client.query(
    `INSERT INTO notifications (user_id, type, title_en, title_hi, body_en, body_hi, trade_id, dispute_id)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
    [
      data.user_id,
      data.type,
      data.title_en,
      data.title_hi,
      data.body_en,
      data.body_hi,
      data.trade_id || null,
      data.dispute_id || null,
    ]
  );
}
