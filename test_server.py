#!/usr/bin/env python3
"""
Simple test server for Kenyan Payroll Management System
This serves the demo HTML file and static assets
"""

import http.server
import socketserver
import os
import webbrowser
from urllib.parse import urlparse

class PayrollTestHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        # Parse the URL
        parsed_path = urlparse(self.path)
        path = parsed_path.path
        
        # Serve demo.html for root path
        if path == '/' or path == '':
            self.path = '/demo.html'
        
        # Call the parent handler
        return super().do_GET()
    
    def end_headers(self):
        # Add CORS headers for local testing
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        super().end_headers()

def start_server(port=8000):
    """Start the test server"""
    try:
        # Change to the project directory
        os.chdir(os.path.dirname(os.path.abspath(__file__)))
        
        # Create server
        with socketserver.TCPServer(("", port), PayrollTestHandler) as httpd:
            print(f"🚀 Kenyan Payroll System Test Server")
            print(f"📍 Serving at: http://localhost:{port}")
            print(f"📁 Directory: {os.getcwd()}")
            print(f"🌐 Demo URL: http://localhost:{port}/demo.html")
            print(f"📋 Available files:")
            
            # List available files
            for root, dirs, files in os.walk('.'):
                # Skip hidden directories
                dirs[:] = [d for d in dirs if not d.startswith('.')]
                level = root.replace('.', '').count(os.sep)
                indent = ' ' * 2 * level
                print(f"{indent}📁 {os.path.basename(root)}/")
                subindent = ' ' * 2 * (level + 1)
                for file in files:
                    if not file.startswith('.'):
                        print(f"{subindent}📄 {file}")
            
            print(f"\n🔧 Press Ctrl+C to stop the server")
            print(f"💡 Tip: Open http://localhost:{port} in your browser")
            
            # Try to open browser automatically
            try:
                webbrowser.open(f'http://localhost:{port}')
                print(f"🌐 Browser opened automatically")
            except:
                print(f"🌐 Please open http://localhost:{port} manually in your browser")
            
            # Start serving
            httpd.serve_forever()
            
    except KeyboardInterrupt:
        print(f"\n🛑 Server stopped by user")
    except OSError as e:
        if "Address already in use" in str(e):
            print(f"❌ Port {port} is already in use. Try a different port:")
            print(f"   python3 test_server.py {port + 1}")
        else:
            print(f"❌ Error starting server: {e}")
    except Exception as e:
        print(f"❌ Unexpected error: {e}")

if __name__ == "__main__":
    import sys
    
    # Get port from command line argument or use default
    port = 8000
    if len(sys.argv) > 1:
        try:
            port = int(sys.argv[1])
        except ValueError:
            print("❌ Invalid port number. Using default port 8000.")
    
    start_server(port)
