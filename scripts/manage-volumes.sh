#!/bin/bash

# WebBadeploy Volume Management Script
# Manage Docker volumes for site data

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

function show_help() {
    cat << EOF
WebBadeploy Volume Management

Usage: $0 [command] [options]

Commands:
    list                List all site volumes
    inspect <volume>    Show detailed volume information
    backup <volume>     Backup a volume to tar.gz
    restore <file>      Restore a volume from backup
    copy <src> <dst>    Copy files from volume to host
    upload <src> <vol>  Upload files from host to volume
    clean               Remove unused volumes
    size                Show volume sizes

Examples:
    $0 list
    $0 inspect php_demo_1759272459_data
    $0 backup php_demo_1759272459_data
    $0 copy php_demo_1759272459_data /tmp/backup
    $0 upload /path/to/files php_demo_1759272459_data

EOF
}

function list_volumes() {
    echo -e "${GREEN}Site Volumes:${NC}"
    docker volume ls --filter "name=php_" --filter "name=laravel_" --filter "name=wordpress_" --format "table {{.Name}}\t{{.Driver}}\t{{.Scope}}"
}

function inspect_volume() {
    local volume=$1
    if [ -z "$volume" ]; then
        echo -e "${RED}Error: Volume name required${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}Volume Details:${NC}"
    docker volume inspect "$volume"
    
    echo -e "\n${GREEN}Volume Contents:${NC}"
    docker run --rm -v "$volume:/data" alpine ls -lah /data
}

function backup_volume() {
    local volume=$1
    if [ -z "$volume" ]; then
        echo -e "${RED}Error: Volume name required${NC}"
        exit 1
    fi
    
    local backup_dir="$PROJECT_DIR/backups"
    mkdir -p "$backup_dir"
    
    local backup_file="$backup_dir/${volume}_$(date +%Y%m%d_%H%M%S).tar.gz"
    
    echo -e "${YELLOW}Backing up volume: $volume${NC}"
    docker run --rm -v "$volume:/data" -v "$backup_dir:/backup" alpine tar czf "/backup/$(basename $backup_file)" -C /data .
    
    echo -e "${GREEN}Backup created: $backup_file${NC}"
    ls -lh "$backup_file"
}

function restore_volume() {
    local backup_file=$1
    if [ -z "$backup_file" ] || [ ! -f "$backup_file" ]; then
        echo -e "${RED}Error: Backup file not found${NC}"
        exit 1
    fi
    
    # Extract volume name from backup filename
    local volume_name=$(basename "$backup_file" | sed 's/_[0-9]*_[0-9]*.tar.gz//')
    
    echo -e "${YELLOW}Restoring to volume: $volume_name${NC}"
    docker volume create "$volume_name"
    docker run --rm -v "$volume_name:/data" -v "$(dirname $backup_file):/backup" alpine tar xzf "/backup/$(basename $backup_file)" -C /data
    
    echo -e "${GREEN}Volume restored: $volume_name${NC}"
}

function copy_from_volume() {
    local volume=$1
    local dest=$2
    
    if [ -z "$volume" ] || [ -z "$dest" ]; then
        echo -e "${RED}Error: Volume and destination required${NC}"
        exit 1
    fi
    
    mkdir -p "$dest"
    echo -e "${YELLOW}Copying from volume: $volume to $dest${NC}"
    docker run --rm -v "$volume:/data" -v "$dest:/dest" alpine cp -r /data/. /dest/
    
    echo -e "${GREEN}Files copied to: $dest${NC}"
}

function upload_to_volume() {
    local src=$1
    local volume=$2
    
    if [ -z "$src" ] || [ -z "$volume" ]; then
        echo -e "${RED}Error: Source and volume required${NC}"
        exit 1
    fi
    
    if [ ! -d "$src" ]; then
        echo -e "${RED}Error: Source directory not found${NC}"
        exit 1
    fi
    
    echo -e "${YELLOW}Uploading to volume: $volume${NC}"
    docker run --rm -v "$volume:/data" -v "$src:/src" alpine cp -r /src/. /data/
    
    echo -e "${GREEN}Files uploaded to volume: $volume${NC}"
}

function clean_volumes() {
    echo -e "${YELLOW}Removing unused volumes...${NC}"
    docker volume prune -f
    echo -e "${GREEN}Cleanup complete${NC}"
}

function show_sizes() {
    echo -e "${GREEN}Volume Sizes:${NC}"
    for volume in $(docker volume ls --filter "name=php_" --filter "name=laravel_" --filter "name=wordpress_" -q); do
        size=$(docker run --rm -v "$volume:/data" alpine du -sh /data | cut -f1)
        echo "$volume: $size"
    done
}

# Main command handler
case "${1:-help}" in
    list)
        list_volumes
        ;;
    inspect)
        inspect_volume "$2"
        ;;
    backup)
        backup_volume "$2"
        ;;
    restore)
        restore_volume "$2"
        ;;
    copy)
        copy_from_volume "$2" "$3"
        ;;
    upload)
        upload_to_volume "$2" "$3"
        ;;
    clean)
        clean_volumes
        ;;
    size)
        show_sizes
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        show_help
        exit 1
        ;;
esac
