# Revisión técnica preproducción (rol: líder técnico)

Este documento consolida mejoras recomendadas antes de pasar a producción para el sistema de test vocacional en **PHP 8.3 + Apache** con persistencia opcional en **MySQL**, orientado a usuarios internos institucionales.

## 1) Lista de mejoras de seguridad

1. **Incorporar autenticación y autorización real para el módulo admin**.
   - Actualmente rutas como `/admin/evaluaciones` y `/admin/evaluaciones/detalle` son accesibles sin control de acceso explícito.
   - Recomendación mínima: login institucional (LDAP/OIDC/SAML) + control de roles (`aplicador`, `psicología`, `admin`).

2. **Agregar protección CSRF en todos los formularios POST**.
   - Formularios críticos (`/`, `/instrucciones/iniciar`, `/prueba/bloque/guardar`, `/prueba/finalizar`) no incluyen token CSRF.
   - Implementar token por sesión + validación en backend.

3. **Endurecer configuración de sesión**.
   - Configurar `session.cookie_secure=1` (en HTTPS), `session.cookie_httponly=1`, `session.cookie_samesite=Lax` o `Strict`, rotación de ID al autenticarse y al iniciar prueba.

4. **Ocultar modo debug y errores detallados en producción**.
   - En producción establecer `environment=production` y `debug=false`.
   - Registrar trazas en logs internos, nunca en respuesta al usuario final.

5. **Manejo seguro de secretos**.
   - Quitar credenciales de DB de `config/app.php` en claro para despliegue.
   - Cargar desde variables de entorno (`APP_ENV`, `DB_HOST`, `DB_USER`, `DB_PASSWORD`, etc.).

6. **Cabeceras HTTP de seguridad en Apache**.
   - Definir al menos: `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`.

7. **Rate limiting y antiflood básico**.
   - Limitar intentos por IP/usuario en rutas sensibles (`/admin/*`, guardado de bloques y finalización).

8. **Trazabilidad/auditoría de operaciones administrativas**.
   - Registrar quién consultó/reimprimió evaluaciones, fecha, IP interna, motivo o ticket.

9. **Política de retención y minimización de datos personales**.
   - Definir periodo de conservación y mecanismo de anonimización/depuración para evaluaciones históricas.

10. **Plan de respaldo y restauración cifrados**.
    - Backups automáticos de MySQL con pruebas periódicas de restauración y cifrado en reposo.

## 2) Lista de mejoras de usabilidad

1. **Agregar guardado automático con feedback visual persistente**.
   - Mostrar “Guardado hace X segundos” por bloque y estado de conectividad.

2. **Incluir barra de progreso global + navegación por bloques contestados/no contestados**.
   - Permitir saltar a un bloque no contestado antes de finalizar.

3. **Mejorar accesibilidad (A11y)**.
   - Revisar contraste, foco visible, navegación por teclado, `aria-live` para alertas, etiquetas explícitas en radios.

4. **Mensajes de error más orientados a acción**.
   - Indicar exactamente qué bloque/pregunta está incompleto y cómo corregirlo.

5. **Mejorar UX de finalización**.
   - Confirmación previa + resumen de bloques faltantes antes de enviar.

6. **Añadir filtros rápidos en admin**.
   - Rangos de fecha predefinidos (hoy, semana, mes), estado de validez, exportación CSV/PDF.

7. **Normalización de nombres y grupos en captura**.
   - Capitalización automática opcional y limpieza de espacios dobles para consistencia.

## 3) Mejoras de rendimiento

1. **Cachear configuración estática del test**.
   - Archivos JSON de escalas/percentiles/reglas pueden precargarse y mantenerse en memoria compartida (OPcache/APCu según infraestructura).

2. **Agregar índices en consultas frecuentes**.
   - Evaluar índices compuestos adicionales para filtros administrativos por fecha/grupo/estado.

3. **Optimizar consultas de listados admin**.
   - Mantener paginación acotada y revisar planes de ejecución (`EXPLAIN`) en datos reales.

4. **Evitar recomputación redundante de catálogos**.
   - Reutilizar estructuras de catálogo y mapeos en ciclo de vida de request.

5. **Habilitar OPcache en producción**.
   - Ajustar `opcache.enable=1`, `opcache.validate_timestamps=0` (con estrategia de despliegue por release inmutable).

6. **Compresión y caché de assets estáticos**.
   - Apache con gzip/br, `Cache-Control` para CSS/JS estáticos versionados.

7. **Revisión de payload en endpoints JSON**.
   - Limitar tamaño de respuesta y retornar solo campos necesarios para paneles.

## 4) Validaciones adicionales recomendadas

1. **Validar transición de flujo con mayor rigor**.
   - No permitir finalizar si no se registró guardado válido de todos los bloques.

2. **Validar coherencia de sexo y percentiles configurados**.
   - Definir tratamiento explícito para `X` (sin percentil, percentil neutro o tabla específica).

3. **Validar rango y formato de fecha en filtros admin**.
   - Actualmente se valida formato `YYYY-MM-DD`; agregar límites (no futuras, ventana máxima, etc.).

4. **Validar unicidad/control de duplicados de evaluaciones en una misma ventana temporal**.
   - Evitar doble envío por refresco/reintento accidental.

5. **Validar longitud máxima de todos los campos en backend de forma alineada a DB**.
   - Confirmar `nombres/apellidos/grupo` contra tamaño real de columnas e inputs.

6. **Validar existencia de configuraciones críticas al iniciar app**.
   - Fail-fast con healthcheck si faltan JSON o hay estructura incompatible.

7. **Validación anti-manipulación de respuestas**.
   - Firmar o verificar integridad básica de bloque/actividad para evitar payload alterado entre cliente y servidor.

## 5) Estructura final recomendada para despliegue

```text
/var/www/test-vocacional/
├── current -> /var/www/test-vocacional/releases/2026-04-15_120000/
├── releases/
│   ├── 2026-04-15_120000/
│   │   ├── public/                # DocumentRoot Apache
│   │   ├── src/
│   │   ├── templates/
│   │   ├── config/
│   │   ├── docs/
│   │   └── vendor/ (si aplica)
├── shared/
│   ├── logs/
│   ├── cache/
│   ├── sessions/
│   └── .env                       # secretos fuera del repo
└── scripts/
    ├── deploy.sh
    ├── migrate.sh
    └── rollback.sh
```

**Recomendaciones de infraestructura:**
- `DocumentRoot` apuntando solo a `public/`.
- Usuario de sistema no privilegiado para Apache/PHP.
- Pipeline de despliegue con releases inmutables + rollback por symlink.
- Migraciones SQL versionadas e idempotentes.
- TLS interno (aunque sea red institucional) y segmentación de red para MySQL.

## 6) Checklist de producción

### Seguridad
- [ ] HTTPS activo y forzado.
- [ ] Headers de seguridad configurados en Apache.
- [ ] CSRF habilitado y validado en todos los POST.
- [ ] Autenticación institucional en módulo admin.
- [ ] Autorización por roles probada.
- [ ] Cookies de sesión endurecidas.
- [ ] `debug=false` y sin exposición de stack traces.
- [ ] Secretos en variables de entorno, no en repositorio.

### Datos y cumplimiento
- [ ] Política de retención de datos definida.
- [ ] Backups automáticos + prueba de restauración exitosa.
- [ ] Accesos administrativos auditados.
- [ ] Diccionario de datos y responsables de custodia definidos.

### Calidad y operación
- [ ] Pruebas unitarias y de integración en verde.
- [ ] Prueba de carga básica (concurrencia esperada institucional).
- [ ] Monitoreo y alertas (errores HTTP, latencia, DB).
- [ ] Healthcheck de aplicación y DB operativo.
- [ ] Plan de rollback probado en ambiente de staging.

### UX y funcionalidad
- [ ] Flujo completo (inicio → instrucciones → prueba → finalización) validado.
- [ ] Validaciones de formulario verificadas en frontend y backend.
- [ ] Accesibilidad mínima (teclado, foco, contraste, lectura de errores).
- [ ] Panel admin validado con filtros reales y paginación.

### Configuración técnica (PHP/Apache/MySQL)
- [ ] PHP 8.3 con OPcache activo.
- [ ] Apache con `AllowOverride`/rewrite conforme a routing definido.
- [ ] Timezone consistente (app, PHP, MySQL, servidor).
- [ ] Índices DB revisados con datos de volumen real.
- [ ] Conexión PDO con usuario de mínimos privilegios.
