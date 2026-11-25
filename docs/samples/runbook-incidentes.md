# Runbook de incidentes de plataforma

## ERR-4521: certificado TLS expirado

El error ERR-4521 aparece cuando el certificado TLS del balanceador ha expirado. Para resolverlo, renueve el certificado con el gestor de certificados y reinicie el listener HTTPS. La rotación automática debe ejecutarse 30 días antes del vencimiento.

## ERR-3307: base de datos sin conexiones disponibles

ERR-3307 indica que el pool de conexiones a PostgreSQL está agotado. Revise consultas lentas con pg_stat_activity y aumente temporalmente el tamaño del pool. Escale a la guardia de datos si persiste más de 15 minutos.
