# Lista de TODOs - Perfil de Usuario

- [x] Ampliar `types.ts` para incluir interface `UserProfile` con datos personales y de empresa  
- [x] Crear componente `profile.vue` que renderice las tarjetas "Tus datos" y "Datos de empresa"  
- [x] Implementar formularios editables en `profile.vue` con validaciones en campos básicos (Nombre, Email, Teléfono, Empresa, Dirección, etc.)  
- [x] Conectar `profile.vue` con el servicio `api.ts` para obtener y actualizar datos del usuario logueado  
- [x] Agregar ruta en el router para que la opción "Usuario" del sidebar apunte al nuevo `profile.vue`  
- [x] Verificar que el guardado de cambios actualice correctamente en el backend y recargue la vista de perfil  
- [x] Crear `UserProfile.vue` para datos personales + subida de imagen  
- [x] Crear `CompanyProfile.vue` para datos empresariales  
- [-] Crear `ProfileStats.vue` para mostrar estadísticas (compra/venta, calificación, opiniones)  
- [-] Añadir lógica para subir y gestionar imagen de perfil reutilizando `DropZone.vue`  
- [-] Configurar nuevas rutas en el router para usuario, empresa y estadísticas  
- [-] Actualizar sidebar con tres opciones: Usuario, Empresa, Estadísticas  
- [-] Implementar consultas API para estadísticas de usuario (ventas, compras, calificaciones, opiniones)  
- [-] Refinar UI/UX para que la vista de perfil siga estilo moderno y atractivo  