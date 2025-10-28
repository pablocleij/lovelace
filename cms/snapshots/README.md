# File Versioning

Every time a file is created or modified through an event, a versioned snapshot is created.

## Structure

```
/cms/snapshots/
  pages/
    home/
      0000001.json  <- version from event 0000001
      0000002.json  <- version from event 0000002
      0000005.json  <- version from event 0000005
  posts/
    0001/
      0000003.json  <- version from event 0000003
      0000007.json  <- version from event 0000007
```

## Manual Rollback

To rollback a file to a previous version:

1. Navigate to the versioned snapshot directory
2. Identify the event ID you want to restore
3. Copy the content from that snapshot
4. Create a new event with a `create_file` patch to restore it

## Usage

Versioned snapshots are automatically created by the replay engine.
Each snapshot preserves the exact state of a file at the time of a specific event.
