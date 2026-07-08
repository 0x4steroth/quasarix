# Quasarix

A lightweight, cross-platform PHP web shell designed for compatibility with both Unix-like operating systems and Microsoft Windows. This project provides a unified interface for interacting with remote systems through PHP, abstracting platform-specific differences to deliver a consistent experience across supported environments.

The web shell automatically adapts its behavior based on the underlying operating system, allowing commands, file operations, and environment interactions to function seamlessly whether the target host is running Linux, Unix, or Windows. It is designed with portability, extensibility, and maintainability in mind, making it suitable for development, testing, research, and controlled laboratory environments.

## Supported Platforms

| Operating System | Status |
|------------------|--------|
| Linux/Unix       | ✅ Supported |
| Windows          | ✅ Supported |

## Design Goals

- Minimize platform-specific code where possible.
- Ensure compatibility with a wide range of PHP versions and web servers.

## Disclaimer

This project is intended for educational purposes, security research, and use within authorized environments only. Always obtain explicit permission before interacting with systems that you do not own or have authorization to test.
