# Overview

nexSEO Plugin is a comprehensive WordPress plugin designed to automate SEO optimization tasks for job-related content and provide advanced webhook integration capabilities. The plugin has been successfully refactored from a monolithic 1289-line single file into a clean, modular architecture with 14 specialized components. It provides automated SEO title and description generation, slug optimization, comprehensive logging capabilities, and a powerful webhook system for external data integration and automatic post creation.

# User Preferences

Preferred communication style: Simple, everyday language.

# System Architecture

## Plugin Architecture
The system follows a modular WordPress plugin architecture with clear separation of concerns:

- **Bootstrap Pattern**: Main plugin file (`nexjob-seo-automation.php`) serves as entry point with constants and initialization
- **Component-Based Design**: Core functionality split into specialized classes in the `includes/` directory
- **Orchestrator Pattern**: Central `NexJobSEOPlugin` class coordinates between components
- **WordPress Integration**: Leverages WordPress hooks, settings API, and cron system

## Core Components

### Settings Management
- Centralized configuration handling through WordPress Settings API
- Default value management system
- Separation of settings logic from business logic

### Post Processing Engine
- Dedicated component for SEO analysis and optimization
- Automated title and description generation
- URL slug optimization functionality

### Logging System
- Database-driven logging with custom table structure
- Log filtering and retrieval capabilities
- Statistics generation for monitoring

### Scheduled Processing
- WordPress cron integration for batch processing
- Custom interval definitions for flexible scheduling
- Batch processing coordination to handle large datasets

### Admin Interface
- WordPress admin page integration
- Real-time statistics display
- Manual processing controls for immediate execution

### AJAX Handler System
- Dedicated endpoints for asynchronous operations
- Log management functionality
- Real-time interface updates

## Webhook System Components

### Webhook Database Management
- Custom database tables for webhook configurations and received data
- Schema creation and maintenance utilities
- Table status monitoring and health checks

### Webhook Data Storage
- Incoming POST data storage and management
- Data parsing and field extraction capabilities
- Status tracking for processed/unprocessed data

### Webhook Manager
- Webhook creation with unique URL generation
- Token management and security handling
- Configuration validation and status management

### Field Mapper
- Intelligent mapping of webhook data to WordPress fields
- Support for core post fields, meta fields, and taxonomies
- Auto-suggestion engine for field mappings

### Webhook Processor
- REST API endpoints for receiving POST requests
- Automatic post creation from webhook data
- Featured image handling from URLs
- Error handling and logging integration

### Webhook Admin Interface
- Complete admin interface for webhook management
- Real-time data fetching and configuration
- Field mapping interface with auto-suggestions
- Statistics and monitoring dashboard

## Design Patterns
- **Single Responsibility Principle**: Each class handles one specific concern
- **Dependency Injection**: Components receive dependencies rather than creating them
- **Event-Driven Architecture**: Uses WordPress hooks for loose coupling
- **Factory Pattern**: Centralized component initialization

# External Dependencies

## WordPress Core Dependencies
- WordPress Settings API for configuration management
- WordPress Cron system for scheduled tasks
- WordPress admin interface framework
- WordPress AJAX handling system
- WordPress database abstraction layer

## WordPress Features Used
- Custom post types and meta fields
- Admin menu and page system
- User capability checking
- Nonce verification for security
- Database table creation and management

## Potential Third-Party Integrations
- SEO analysis APIs for content optimization
- Job board platforms for content sourcing
- Analytics services for performance tracking
- Content generation services for automated text creation