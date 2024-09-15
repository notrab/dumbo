# Benchmarks Example

This example has some basic benchmarks for Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Run the benchmark:

   ```bash
     php benchmark.php
   ```

## Example results

```bash
Benchmark Results:
Concurrency: 50

Scenario: simple
Method: GET, Path: /, Requests: 1000
Total Time: 0.1485 seconds
Average Time per Request: 0.1485 ms
Requests per Second: 6,733.10

Scenario: echo
Method: POST, Path: /echo, Requests: 500
Total Time: 0.0756 seconds
Average Time per Request: 0.1512 ms
Requests per Second: 6,615.06

Scenario: cpu
Method: GET, Path: /cpu, Requests: 100
Total Time: 1.4337 seconds
Average Time per Request: 14.3370 ms
Requests per Second: 69.75

Scenario: db
Method: GET, Path: /db/{id}, Requests: 500
Total Time: 27.4210 seconds
Average Time per Request: 54.8419 ms
Requests per Second: 18.23

Scenario: large
Method: GET, Path: /large, Requests: 200
Total Time: 0.0951 seconds
Average Time per Request: 0.4757 ms
Requests per Second: 2,102.01
```
