#!/usr/bin/env python3
"""
PHP Syntax Checker for Kenyan Payroll Management System
Checks PHP files for basic syntax issues without requiring PHP installation
"""

import os
import re
import sys

def check_php_syntax(file_path):
    """Check basic PHP syntax issues"""
    issues = []
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
            lines = content.split('\n')
        
        # Check for common syntax issues
        for i, line in enumerate(lines, 1):
            line_stripped = line.strip()
            
            # Check for missing semicolons (basic check)
            if (line_stripped.endswith('}') == False and 
                line_stripped.endswith(';') == False and 
                line_stripped.endswith('{') == False and
                line_stripped.endswith(':') == False and
                line_stripped != '' and
                not line_stripped.startswith('//') and
                not line_stripped.startswith('/*') and
                not line_stripped.startswith('*') and
                not line_stripped.startswith('<?php') and
                not line_stripped.startswith('?>') and
                not line_stripped.startswith('#') and
                'if (' not in line_stripped and
                'else' not in line_stripped and
                'foreach (' not in line_stripped and
                'while (' not in line_stripped and
                'function ' not in line_stripped and
                'class ' not in line_stripped):
                
                # Additional checks to avoid false positives
                if ('=' in line_stripped or 
                    'echo ' in line_stripped or 
                    'print ' in line_stripped or
                    'return ' in line_stripped or
                    'include ' in line_stripped or
                    'require ' in line_stripped):
                    issues.append(f"Line {i}: Possible missing semicolon")
            
            # Check for unmatched quotes (basic)
            single_quotes = line_stripped.count("'") - line_stripped.count("\\'")
            double_quotes = line_stripped.count('"') - line_stripped.count('\\"')
            
            if single_quotes % 2 != 0:
                issues.append(f"Line {i}: Unmatched single quote")
            if double_quotes % 2 != 0:
                issues.append(f"Line {i}: Unmatched double quote")
            
            # Check for unmatched parentheses
            open_parens = line_stripped.count('(')
            close_parens = line_stripped.count(')')
            if open_parens != close_parens:
                issues.append(f"Line {i}: Unmatched parentheses")
        
        # Check for unmatched braces in entire file
        open_braces = content.count('{')
        close_braces = content.count('}')
        if open_braces != close_braces:
            issues.append(f"File: Unmatched braces ({open_braces} open, {close_braces} close)")
        
        return issues
        
    except Exception as e:
        return [f"Error reading file: {e}"]

def check_all_php_files():
    """Check all PHP files in the project"""
    print("🔍 Checking PHP syntax in Kenyan Payroll Management System")
    print("=" * 60)
    
    php_files = []
    total_issues = 0
    
    # Find all PHP files
    for root, dirs, files in os.walk('.'):
        # Skip hidden directories
        dirs[:] = [d for d in dirs if not d.startswith('.')]
        
        for file in files:
            if file.endswith('.php'):
                php_files.append(os.path.join(root, file))
    
    if not php_files:
        print("❌ No PHP files found in current directory")
        return
    
    print(f"📁 Found {len(php_files)} PHP files:")
    
    for file_path in php_files:
        print(f"\n📄 Checking: {file_path}")
        issues = check_php_syntax(file_path)
        
        if issues:
            print(f"⚠️  Found {len(issues)} potential issues:")
            for issue in issues:
                print(f"   • {issue}")
            total_issues += len(issues)
        else:
            print("✅ No obvious syntax issues found")
    
    print("\n" + "=" * 60)
    if total_issues == 0:
        print("🎉 All PHP files passed basic syntax check!")
        print("💡 Note: This is a basic check. Full PHP syntax validation requires PHP installation.")
    else:
        print(f"⚠️  Found {total_issues} potential issues across all files")
        print("💡 Please review the issues above. Some may be false positives.")
    
    print("\n📋 Project Structure:")
    for file_path in sorted(php_files):
        file_size = os.path.getsize(file_path)
        print(f"   📄 {file_path} ({file_size} bytes)")

if __name__ == "__main__":
    check_all_php_files()
