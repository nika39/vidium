# P2P Video Analytics Platform 📊

A high-performance B2B SaaS analytics platform built to track, aggregate, and visualize Peer-to-Peer (P2P) vs. HTTP video streaming metrics. Designed to handle high-frequency telemetry data from thousands of concurrent video players while maintaining a minimal server footprint.

## 🚀 Overview

This platform provides website owners (clients) with a real-time dashboard to monitor their video traffic offload. By utilizing P2P streaming technology, clients can save significant bandwidth. This system securely ingests periodic "heartbeats" from their video players, processes the data through a highly optimized caching pipeline, and presents actionable insights.

## ✨ Key Features

- **High-Throughput Data Ingestion:** Capable of handling thousands of requests per minute using Laravel Octane and Redis pipelining.
- **Smart Data Aggregation:** Uses time-series dimensional modeling to aggregate millions of pings into compact, hourly database records.
- **Real-Time B2B Dashboard:** A responsive, interactive dashboard built with React and Inertia.js, featuring time-series charts, browser/OS distribution, and bandwidth savings calculators.
- **Automated Data Lifecycle:** Implements background pruning of historical data to maintain optimal database size and query performance.
- **Resilient Security:** Features dynamic rate-limiting, structural payload validation, and license-key verification to ensure data integrity and prevent abuse.

## 🛠️ Tech Stack

- **Backend:** Laravel 13, Laravel Octane
- **Frontend:** React.js, Inertia.js, Tailwind CSS
- **Database:** MySQL (Structured for Time-Series Data)
- **In-Memory Store:** Redis (For buffering, pipelining, and caching)

## 🏗️ Architecture Highlight: The Ingestion Pipeline

To prevent database bottlenecking from continuous API requests, the system implements a **Buffer-and-Batch** architecture:

1. **Receive & Validate:** The API receives payload data from the client's video player. A _Thin Controller, Fat Service_ pattern delegates the data to an ingestion service.
2. **Redis Buffer (Pipeline):** Data is NOT written directly to MySQL. Instead, metrics are atomically incremented in Redis (`hincrby`) using time-bound dimensional keys (e.g., `SiteID:Hour:Browser:OS`).
3. **Background Sync:** A scheduled background job runs periodically to flush the Redis buffer.
4. **Bulk Upsert:** The flushed data is sent to MySQL using a Bulk Upsert (`ON DUPLICATE KEY UPDATE`). The database natively aggregates the numerical values, resulting in a massively reduced row count and lightning-fast read queries for the Dashboard.

## 💻 Local Setup & Installation

### Prerequisites

...

### Installation Steps

...
