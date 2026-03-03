-- Índices optimizados para queries de polling del chat
-- Ejecutar este script para mejorar performance de consultas

-- Índice para tabla de mensajes de productos (pre-compra)
-- Optimiza: SELECT * FROM wp_motorlan_product_messages WHERE product_id = X AND room_key = Y AND created_at > Z
ALTER TABLE wp_motorlan_product_messages 
ADD INDEX IF NOT EXISTS idx_polling_product (product_id, room_key, created_at);

-- Índice adicional para conteo de no leídos por usuario
ALTER TABLE wp_motorlan_product_messages 
ADD INDEX IF NOT EXISTS idx_unread_check (product_id, room_key, user_id, created_at);

-- Índice para tabla de mensajes de compras (post-compra)
-- Optimiza: SELECT * FROM wp_motorlan_purchase_messages WHERE purchase_uuid = X AND created_at > Y
ALTER TABLE wp_motorlan_purchase_messages 
ADD INDEX IF NOT EXISTS idx_polling_purchase (purchase_uuid, created_at);

-- Índice para búsqueda por ID de compra (migración de legacy)
ALTER TABLE wp_motorlan_purchase_messages 
ADD INDEX IF NOT EXISTS idx_purchase_id (purchase_id, created_at);

-- Índice para tabla de lecturas de salas (unread counts)
ALTER TABLE wp_motorlan_product_room_reads 
ADD INDEX IF NOT EXISTS idx_user_room_reads (user_id, product_id, room_key);

-- Verificar los índices creados
-- Descomentar para ejecutar verificación:
-- SHOW INDEX FROM wp_motorlan_product_messages WHERE Key_name LIKE 'idx_%';
-- SHOW INDEX FROM wp_motorlan_purchase_messages WHERE Key_name LIKE 'idx_%';
-- SHOW INDEX FROM wp_motorlan_product_room_reads WHERE Key_name LIKE 'idx_%';
