import yfinance as yf
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.linear_model import LinearRegression
from sklearn.metrics import r2_score
import matplotlib.pyplot as plt

def get_stock_data(ticker, years=5):
    """Fetch stock data for a given ticker."""
    end_date = datetime.now()
    start_date = end_date - timedelta(days=years*365)

    try:
        stock = yf.Ticker(ticker)
        df = stock.history(start=start_date, end=end_date)
        return df['Close'] if not df.empty else None
    except:
        return None

def calculate_linearity_score(prices):
    """Calculate R-squared score for stock prices against linear regression."""
    if prices is None or len(prices) < 2:
        return 0

    X = np.arange(len(prices)).reshape(-1, 1)
    y = prices.values.reshape(-1, 1)

    model = LinearRegression()
    model.fit(X, y)

    return r2_score(y, model.predict(X))

def calculate_price_increase(prices):
    """Calculate percentage increase over the period."""
    if prices is None or len(prices) < 2:
        return 0

    initial_price = prices.iloc[0]
    final_price = prices.iloc[-1]
    return ((final_price - initial_price) / initial_price) * 100

def analyze_stocks(tickers_by_industry):
    """Analyze stocks by industry and find most linear growth patterns."""
    results = []

    for industry, tickers in tickers_by_industry.items():
        for ticker in tickers:
            prices = get_stock_data(ticker)
            if prices is not None:
                linearity_score = calculate_linearity_score(prices)
                price_increase = calculate_price_increase(prices)
                results.append({
                    'ticker': ticker,
                    'industry': industry,
                    'linearity_score': linearity_score,
                    'price_increase': price_increase,
                    'prices': prices
                })

    return pd.DataFrame(results).sort_values('linearity_score', ascending=False)

def plot_most_linear_stock(results):
    """Plot the stock with the highest linearity score."""
    if len(results) == 0:
        return

    most_linear = results.iloc[0]
    prices = most_linear['prices']

    plt.figure(figsize=(12, 6))

    # Plot actual prices
    plt.plot(prices.index, prices.values, label='Actual Price', color='blue')

    # Plot linear regression line
    X = np.arange(len(prices)).reshape(-1, 1)
    y = prices.values.reshape(-1, 1)
    model = LinearRegression()
    model.fit(X, y)
    plt.plot(prices.index, model.predict(X), label='Linear Trend', color='red', linestyle='--')

    plt.title(f'5-Year Price History of {most_linear["ticker"]} (R² = {most_linear["linearity_score"]:.3f})')
    plt.xlabel('Date')
    plt.ylabel('Price ($)')
    plt.legend()
    plt.grid(True)
    plt.show()

# Expanded list of stocks by industry
tickers_by_industry = {
    'Technology': [
        'AAPL', 'MSFT', 'GOOGL', 'META', 'NVDA', 'ADBE', 'CRM', 'CSCO', 'INTC',
        'AMD', 'ORCL', 'IBM', 'QCOM', 'TXN', 'NOW','ANET', 'JNPR',
        'FFIV', 'EPAM', 'NET', 'AKAM','IBM', 'PLTR', 'AI', 'PATH', 'SNOW', 'CRWD', 'DDOG',
        'NOW', 'TEAM', 'ZS', 'PANW', 'FTNT', 'CYBR'
    ],
    'Healthcare': [
        'JNJ', 'PFE', 'UNH', 'ABBV', 'MRK', 'TMO', 'ABT', 'DHR', 'BMY',
        'LLY', 'AMGN', 'GILD', 'ISRG', 'REGN', 'VRTX'
    ],
    'Finance': [
        'JPM', 'BAC', 'WFC', 'GS', 'MS', 'BLK', 'C', 'SPGI', 'AXP',
        'V', 'MA', 'SCHW', 'USB', 'PNC', 'TFC'
    ],
    'Consumer': [
        'PG', 'KO', 'PEP', 'WMT', 'COST', 'NKE', 'MCD', 'SBUX', 'DIS',
        'HD', 'LOW', 'TGT', 'AMZN', 'TSLA', 'F'
    ],
    'Industrial': [
        'GE', 'BA', 'CAT', 'MMM', 'HON', 'UPS', 'FDX', 'RTX', 'LMT',
        'DE', 'EMR', 'ETN', 'ITW', 'CMI', 'ROK'
    ]
}

# Run analysis
results = analyze_stocks(tickers_by_industry)

# Create a formatted table with all stocks
table = results[['ticker', 'industry', 'linearity_score', 'price_increase']].copy()
table['linearity_score'] = table['linearity_score'].apply(lambda x: f"{x:.3f}")
table['price_increase'] = table['price_increase'].apply(lambda x: f"{x:.1f}%")
table.columns = ['Ticker', 'Industry', 'Linearity (R²)', '5-Year Increase']

# Print full table
print("\nAll Stocks Ranked by Price Linearity:")
print(table.to_string(index=False))

# Plot the most linear stock
plot_most_linear_stock(results)
