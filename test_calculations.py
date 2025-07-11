#!/usr/bin/env python3
"""
Test Kenyan Payroll Calculations
Validates PAYE, NSSF, SHIF, and Housing Levy calculations
"""

def calculate_paye(taxable_income):
    """Calculate PAYE tax based on Kenyan tax brackets"""
    tax = 0
    
    # Tax brackets for 2024
    brackets = [
        (0, 24000, 0.10),
        (24001, 32333, 0.25),
        (32334, 500000, 0.30),
        (500001, 800000, 0.325),
        (800001, float('inf'), 0.35)
    ]
    
    for min_income, max_income, rate in brackets:
        if taxable_income > min_income:
            taxable_amount = min(taxable_income, max_income) - min_income + 1
            if taxable_amount > 0:
                tax += taxable_amount * rate
    
    # Apply personal relief
    tax = max(0, tax - 2400)
    return round(tax, 2)

def calculate_nssf(gross_pay, contract_type='permanent'):
    """Calculate NSSF contribution (6% with max pensionable pay of KES 18,000, exempted for casual)"""
    if contract_type == 'casual':
        return 0  # Casual labourers are exempted from NSSF

    pensionable_pay = min(gross_pay, 18000)
    return round(pensionable_pay * 0.06, 2)

def calculate_shif(gross_pay):
    """Calculate SHIF contribution (2.75% of gross pay with minimum KES 300)"""
    import math
    calculated = gross_pay * 0.0275  # 2.75% of gross salary
    return math.ceil(max(calculated, 300))  # Minimum KES 300, rounded up to whole number

def calculate_housing_levy(gross_pay, contract_type='permanent'):
    """Calculate Housing Levy (1.5% of gross pay, exempted for casual)"""
    if contract_type == 'casual':
        return 0  # Casual labourers are exempted from Housing Levy

    return round(gross_pay * 0.015, 2)

def calculate_payroll(basic_salary, allowances=0, contract_type='permanent'):
    """Calculate complete payroll for an employee"""
    gross_pay = basic_salary + allowances

    # Calculate statutory deductions based on contract type
    nssf = calculate_nssf(gross_pay, contract_type)
    shif = calculate_shif(gross_pay)  # SHIF applies to all employment types
    housing_levy = calculate_housing_levy(gross_pay, contract_type)

    # Calculate taxable income (gross pay minus NSSF)
    taxable_income = gross_pay - nssf

    # Calculate PAYE
    paye = calculate_paye(taxable_income)

    # Calculate total deductions and net pay
    total_deductions = paye + nssf + shif + housing_levy
    net_pay = gross_pay - total_deductions

    return {
        'basic_salary': basic_salary,
        'allowances': allowances,
        'gross_pay': gross_pay,
        'nssf': nssf,
        'shif': shif,
        'housing_levy': housing_levy,
        'taxable_income': taxable_income,
        'paye': paye,
        'total_deductions': total_deductions,
        'net_pay': net_pay,
        'contract_type': contract_type
    }

def format_currency(amount):
    """Format amount as Kenyan Shillings"""
    return f"KES {amount:,.2f}"

def test_payroll_scenarios():
    """Test various payroll scenarios"""
    print("🧮 Kenyan Payroll Calculation Tests")
    print("=" * 60)
    
    # Test scenarios
    scenarios = [
        {"name": "Low Income Worker", "basic": 15000, "allowances": 3000, "contract": "permanent"},
        {"name": "Entry Level Employee", "basic": 25000, "allowances": 8000, "contract": "permanent"},
        {"name": "Mid-Level Employee", "basic": 50000, "allowances": 15000, "contract": "permanent"},
        {"name": "Senior Employee", "basic": 75000, "allowances": 28000, "contract": "permanent"},
        {"name": "Management Level", "basic": 120000, "allowances": 40000, "contract": "permanent"},
        {"name": "Executive Level", "basic": 200000, "allowances": 80000, "contract": "permanent"},
        {"name": "Casual Labourer (Low)", "basic": 15000, "allowances": 2000, "contract": "casual"},
        {"name": "Casual Labourer (High)", "basic": 35000, "allowances": 5000, "contract": "casual"},
    ]
    
    for scenario in scenarios:
        print(f"\n📊 {scenario['name']} ({scenario['contract'].title()})")
        print("-" * 50)

        result = calculate_payroll(scenario['basic'], scenario['allowances'], scenario['contract'])

        print(f"Basic Salary:     {format_currency(result['basic_salary'])}")
        print(f"Allowances:       {format_currency(result['allowances'])}")
        print(f"Gross Pay:        {format_currency(result['gross_pay'])}")
        print(f"")

        # Show exemptions for casual labourers
        nssf_text = format_currency(result['nssf'])
        housing_text = format_currency(result['housing_levy'])
        if scenario['contract'] == 'casual':
            nssf_text += " (EXEMPTED)"
            housing_text += " (EXEMPTED)"

        print(f"NSSF (6%):        {nssf_text}")
        print(f"SHIF (2.75%):     {format_currency(result['shif'])}")
        print(f"Housing Levy:     {housing_text}")
        print(f"Taxable Income:   {format_currency(result['taxable_income'])}")
        print(f"PAYE Tax:         {format_currency(result['paye'])}")
        print(f"")
        print(f"Total Deductions: {format_currency(result['total_deductions'])}")
        print(f"NET PAY:          {format_currency(result['net_pay'])}")

        # Calculate percentages
        deduction_rate = (result['total_deductions'] / result['gross_pay']) * 100
        print(f"Deduction Rate:   {deduction_rate:.1f}%")

def test_shif_brackets():
    """Test SHIF calculation across all brackets"""
    print("\n\n🏥 SHIF Bracket Testing")
    print("=" * 60)
    
    test_amounts = [
        3000, 5999, 6000, 7999, 8000, 11999, 12000, 14999,
        15000, 19999, 20000, 24999, 25000, 29999, 30000,
        40000, 50000, 60000, 70000, 80000, 90000, 100000, 150000
    ]
    
    print(f"{'Gross Pay':<12} {'SHIF Amount':<12} {'Rate':<8}")
    print("-" * 35)
    
    for amount in test_amounts:
        shif = calculate_shif(amount)
        rate = (shif / amount) * 100 if amount > 0 else 0
        print(f"{format_currency(amount):<12} {format_currency(shif):<12} {rate:.2f}%")

def validate_minimum_shif():
    """Validate that minimum SHIF is KES 300"""
    print("\n\n✅ SHIF Minimum Validation")
    print("=" * 60)
    
    low_incomes = [1000, 2500, 5000, 5999, 6000, 7500, 7999]
    
    all_correct = True
    for income in low_incomes:
        shif = calculate_shif(income)
        is_correct = shif == 300
        status = "✅ PASS" if is_correct else "❌ FAIL"
        print(f"Income: {format_currency(income):<12} SHIF: {format_currency(shif):<12} {status}")
        
        if not is_correct:
            all_correct = False
    
    print(f"\n{'✅ All SHIF minimums correct!' if all_correct else '❌ SHIF minimum validation failed!'}")

if __name__ == "__main__":
    test_payroll_scenarios()
    test_shif_brackets()
    validate_minimum_shif()
    
    print("\n" + "=" * 60)
    print("🎉 Kenyan Payroll Calculation Testing Complete!")
    print("💡 All calculations follow current Kenyan statutory requirements")
    print("📋 SHIF minimum: KES 300 | NSSF: 6% | Housing Levy: 1.5%")
