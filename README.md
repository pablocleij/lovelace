# lovelace

A file-based, event-sourced CMS managed entirely through conversational AI.

No database, no admin panel—just natural language.

## Architecture

lovelace uses the [Swis Agents SDK](https://github.com/swisnl/agents-sdk) for AI orchestration with a clean, organized structure:

### Directory Structure

```
src/
├── Agents/         # AI agents using Swis SDK
│   └── LovelaceAgent.php
├── Tools/          # CMS operation tools
│   ├── CreatePageTool.php
│   ├── CreateCollectionTool.php
│   ├── UpdateContentTool.php
│   ├── DeleteContentTool.php
│   ├── UpdateThemeTool.php
│   ├── AnalyzeSchemaTool.php
│   └── UpdateNavigationTool.php
├── Services/       # Business logic
│   ├── EventService.php
│   ├── SchemaService.php
│   ├── ApiKeyService.php
│   └── SnapshotService.php
├── Http/           # HTTP request handlers
│   └── ApiController.php
└── Config/         # Configuration management
    └── ConfigService.php
```

### Main Components

#### LovelaceAgent
The primary conversational interface that coordinates all CMS operations through specialized tools. Powered by the Swis Agents SDK orchestrator.

#### Tools
Each CMS operation is implemented as a Swis SDK tool with proper parameter validation:
- **CreatePageTool** - Creates new pages with sections
- **CreateCollectionTool** - Creates content types (testimonials, products, etc.)
- **UpdateContentTool** - Updates existing content
- **DeleteContentTool** - Deletes content (with confirmation)
- **UpdateThemeTool** - Modifies theme colors and fonts
- **AnalyzeSchemaTool** - Infers schemas from content
- **UpdateNavigationTool** - Manages navigation menu

#### Services
Business logic extracted into single-responsibility classes:
- **EventService** - Event creation, writing, hashing, signing
- **SchemaService** - Schema validation, form generation
- **ApiKeyService** - API key encryption and provider management
- **SnapshotService** - Snapshot rebuilding and management

#### Streaming
Real-time streaming responses use the Orchestrator's `runStreamed()` method for word-by-word output from OpenAI/Claude APIs.

## Installation

1. Clone the repository
2. Run `composer install`
3. Configure your API key through the 🔑 API button in the UI
4. Start chatting with the AI to build your site

## Testing

Run the service tests:
```bash
php tests/test_services.php
```

## Event Sourcing

All changes are stored as immutable events with:
- SHA-256 hash chains
- HMAC-SHA256 signatures
- Audit logging

## Benefits

**Before Refactor:**
- 1204-line monolithic api.php
- Mixed concerns (HTTP, AI, business logic, data access)
- Hard to test, maintain, and extend
- Custom streaming implementation

**After Refactor:**
- Clean separation of concerns
- Each component has single responsibility
- Testable services and tools
- Standard Swis SDK patterns
- Easy to add new tools/agents
- Professional architecture
- Built-in streaming support
- api.php reduced to 27 lines
