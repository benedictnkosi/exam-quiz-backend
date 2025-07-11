#!/bin/bash

# Generate Dummy Commuters Script
# This script generates dummy data for commuters and commute distances

echo "🚗 Generating Dummy Commuters and Distances"
echo "=========================================="

# Default values
COUNT=50
DRIVERS_RATIO=0.3
WITH_DISTANCES=false
CLEAR_EXISTING=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -c|--count)
            COUNT="$2"
            shift 2
            ;;
        -d|--drivers-ratio)
            DRIVERS_RATIO="$2"
            shift 2
            ;;
        -w|--with-distances)
            WITH_DISTANCES=true
            shift
            ;;
        -x|--clear-existing)
            CLEAR_EXISTING=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  -c, --count NUMBER        Number of commuters to generate (default: 50)"
            echo "  -d, --drivers-ratio RATIO Ratio of drivers (0.0-1.0, default: 0.3)"
            echo "  -w, --with-distances      Generate commute distances between commuters"
            echo "  -x, --clear-existing      Clear existing commuters and distances before generating"
            echo "  -h, --help                Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                                    # Generate 50 commuters (30% drivers)"
            echo "  $0 -c 100 -d 0.4                     # Generate 100 commuters (40% drivers)"
            echo "  $0 -c 25 -w                          # Generate 25 commuters with distances"
            echo "  $0 -c 75 -d 0.5 -w -x                # Clear existing data and generate 75 commuters (50% drivers) with distances"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use -h or --help for usage information"
            exit 1
            ;;
    esac
done

# Build command
CMD="php bin/console app:generate-dummy-commuters --count=$COUNT --drivers-ratio=$DRIVERS_RATIO"

if [ "$WITH_DISTANCES" = true ]; then
    CMD="$CMD --with-distances"
fi

if [ "$CLEAR_EXISTING" = true ]; then
    CMD="$CMD --clear-existing"
fi

echo "Command: $CMD"
echo ""

# Execute command
eval $CMD

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Dummy data generation completed successfully!"
    echo ""
    echo "📊 Summary:"
    echo "  - Commuters generated: $COUNT"
    echo "  - Drivers ratio: $DRIVERS_RATIO"
    if [ "$WITH_DISTANCES" = true ]; then
        echo "  - Commute distances: Generated"
    else
        echo "  - Commute distances: Not generated"
    fi
    if [ "$CLEAR_EXISTING" = true ]; then
        echo "  - Existing data: Cleared"
    else
        echo "  - Existing data: Preserved"
    fi
else
    echo ""
    echo "❌ Error: Dummy data generation failed!"
    exit 1
fi 