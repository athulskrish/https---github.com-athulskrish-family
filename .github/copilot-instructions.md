# Family Tree Application - AI Agent Guidelines

## Project Overview
This is a PHP-based family tree management application that allows users to create, manage and share family trees. The application uses a MySQL database and follows a traditional PHP architecture with direct file inclusion.

## Core Architecture

### Directory Structure
- `includes/`: Core functionality, configuration and utilities
- `assets/`: Static resources (CSS, JS, images)
- `templates/`: Reusable page components (header, footer)
- `views/`: Page-specific display logic

### Key Components
1. **Database Layer** (`includes/db.php`)
   - Uses MySQL with prepared statements
   - See `database.sql` for complete table structure
   - Relationships are stored in `family_relationships` table with types: parent, child, spouse, sibling

2. **Security Layer** (`includes/security.php`, `includes/SecurityMiddleware.php`)
   - CSRF protection required for forms
   - Rate limiting implemented via `RateLimit` class
   - Input sanitization through `sanitize_input()` function

3. **Authentication** (`includes/auth.php`)
   - Session-based authentication
   - Strict password requirements (12+ chars)
   - Rate-limited login attempts (5 max)

## Key Patterns

### Family Tree Rendering
The tree rendering logic in `functions.php` uses a recursive approach:
```php
renderFamilyTree() -> renderFamilyUnit() -> renderMemberCard()
```

### Access Control
Tree access is managed through:
1. Owner relationship (in `family_trees` table)
2. Shared access (in `tree_access` table with levels: view, edit, admin)
```php
can_access_tree($tree_id) // Central access control function
```

### Media Handling
- Photos stored in `assets/img/members/`
- Strict MIME type validation for uploads
- 10MB file size limit

## Configuration

### Development vs Production
Key settings in `includes/config.php`:
- Development: Error reporting on, HTTPS off
- Production: Error reporting off, HTTPS required
- Update BASE_URL constant for deployment

### Database Setup
1. Import `database.sql` for complete table structure
2. Configure connection in `includes/config.php`
3. SSL/TLS settings available for production

## Common Tasks

### Adding New Member Fields
1. Add column to `family_members` table
2. Update `renderMemberCard()` in `functions.php`
3. Add field to member forms

### Extending Relationships
1. Add new type to `relationship_type` ENUM in `family_relationships` table
2. Update `get_relationship_type()` in `functions.php`
3. Update tree rendering logic if needed

## Testing
- Always test rate limiting with multiple rapid requests
- Test all uploads with various file types
- Verify CSRF protection on all forms

## Development Workflow
1. Local development with error reporting on
2. Test all uploads with various file types
3. Verify CSRF protection on all forms
4. Check rate limiting on sensitive endpoints
