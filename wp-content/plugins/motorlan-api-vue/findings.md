# Findings: Notification System Analysis

## Identified Notification Events

| Event Name | Source File | Current Status |
|------------|-------------|----------------|
| `motorlan_user_welcome` | `includes/api/session-routes.php` | Direct Call |
| `motorlan_publication_approved` | `includes/api/admin-approval-routes.php` | Direct Call |
| `motorlan_publication_rejected` | `includes/api/admin-approval-routes.php` | Direct Call |
| `motorlan_admin_contact_publisher` | `includes/api/admin-publications-routes.php` | Direct Call |
| `motorlan_offer_created` | `includes/api/offers-routes.php` | Direct Call |
| `motorlan_offer_status_updated` | `includes/api/offers-routes.php` | Direct Call |
| `motorlan_manual_sale_created` | `includes/api/sales-routes.php` | Direct Call |
| `motorlan_new_purchase` | `includes/api/purchases-routes.php` | Direct Call |
| `motorlan_new_question` | `includes/api/questions-routes.php` | Direct Call |
| `motorlan_new_chat_message` | `includes/api/products/controllers/class-product-chat-controller.php` | Direct Call |
| `motorlan_new_purchase_message` | `includes/api/purchases/controllers/class-purchase-chat-controller.php` | Direct Call |
| `motorlan_password_reset_code` | `includes/api/profile-routes.php` | Direct Call |
| `motorlan_user_interested` | `includes/api/publicaciones/callbacks/add-user-favorite.php` | **MISSING** |
| `motorlan_publication_sold_manually` | `includes/api/publicaciones/callbacks/update-publicacion-status.php` | **MISSING** |
| `motorlan_new_review` | `includes/api/reviews/controllers/class-motorlan-reviews-controller.php` | **MISSING** |

## Architectural Decision
- Use WordPress `do_action` for loose coupling.
- Create `Motorlan_Notification_Listener` to handle all events.
- Keep `Motorlan_Notification_Manager` as the service responsible for actual delivery (DB & Email).
