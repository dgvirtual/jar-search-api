#!/bin/bash

## this is a temporary script to be used to fill the database with data from journal after the initial import; 
## the numbers list should be filled with ids of journals to import
## regularly disabled
exit

# Array of numbers to iterate through, reversed
numbers=(289169 289368 289468 289568 289668 289768 289868 289769 289968 290068 290069 290168 290268 290368 290269)
#numbers=(289668 290368)
#numbers=(289668)

# Iterate through the numbers and execute the command
for number in "${numbers[@]}"; do
    php scrapjournal.php journal "$number" report
    echo 
    echo
    sleep 1
done