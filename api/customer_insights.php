<?php
require __DIR__ . '/_bootstrap.php';
$uid = require_login();

try {
  // Get 14 days of data
  $stmt = $pdo->prepare("
    SELECT * FROM churn_data 
    WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    ORDER BY date DESC
  ");
  $stmt->execute([$uid]);
  $data14Days = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Get latest prediction
  $predStmt = $pdo->prepare("
    SELECT risk_percentage, risk_level, factors, description
    FROM churn_predictions 
    WHERE user_id = ? 
    ORDER BY created_at DESC LIMIT 1
  ");
  $predStmt->execute([$uid]);
  $prediction = $predStmt->fetch(PDO::FETCH_ASSOC) ?: [];
  
  // If no 14-day data, try to get any recent data
  if (empty($data14Days)) {
    $fallbackStmt = $pdo->prepare("
      SELECT * FROM churn_data 
      WHERE user_id = ? 
      ORDER BY date DESC LIMIT 5
    ");
    $fallbackStmt->execute([$uid]);
    $data14Days = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
  // Handle completely empty data case
  if (empty($data14Days)) {
    json_ok([
      'loyalCustomers' => 0,
      'avgPurchaseValue' => 0.00,
      'loyaltyRate' => 85.0, // Default optimistic rate
      'segmentation' => ['New business - building customer base', 'Early stage analytics'],
      'purchasePatterns' => ['Setting up data collection', 'Begin tracking customer behavior'],
      'riskIndicators' => ['Insufficient historical data', 'Establishing baseline metrics'],
      'riskLevel' => 'Low',
      'riskPercentage' => 15.0, // Conservative default
      'trendDirection' => 'Establishing Baseline',
      'trendPatterns' => ['Initial setup phase', 'Collecting first customer data points', 'Building predictive models']
    ]);
    exit;
  }
  
  $today = $data14Days[0] ?? [];
  $historical = array_slice($data14Days, 1);
  
  // Calculate metrics with fallbacks
  $todayReceipts = max(0, (int)($today['receipt_count'] ?? 0));
  $todaySales = max(0, (float)($today['sales_volume'] ?? 0));
  $todayTraffic = max(0, (int)($today['customer_traffic'] ?? 0));
  
  // Historical averages with safety checks
  $avgReceipts = 0;
  $avgSales = 0;
  $avgTraffic = 0;
  
  if (count($historical) > 0) {
    $avgReceipts = array_sum(array_column($historical, 'receipt_count')) / count($historical);
    $avgSales = array_sum(array_column($historical, 'sales_volume')) / count($historical);
    $avgTraffic = array_sum(array_column($historical, 'customer_traffic')) / count($historical);
  }
  
  // Calculate percentage changes with safety checks
  $receiptChange = $avgReceipts > 0 ? (($todayReceipts - $avgReceipts) / $avgReceipts) * 100 : 0;
  $salesChange = $avgSales > 0 ? (($todaySales - $avgSales) / $avgSales) * 100 : 0;
  $trafficChange = $avgTraffic > 0 ? (($todayTraffic - $avgTraffic) / $avgTraffic) * 100 : 0;
  
  // Risk analysis with better defaults
  $riskPct = 25.0; // Default moderate risk
  $riskLevel = 'Medium';
  
  if (!empty($prediction)) {
    $riskPct = isset($prediction['risk_percentage']) ? (float)$prediction['risk_percentage'] : 25.0;
    if ($riskPct <= 1.0) $riskPct *= 100;
    $riskPct = min(100, max(0, $riskPct));
    $riskLevel = $prediction['risk_level'] ?? 'Medium';
  }
  
  // Customer insights with meaningful data
  $avgPurchaseValue = $todayReceipts > 0 ? ($todaySales / $todayReceipts) : ($avgSales > 0 && $avgReceipts > 0 ? ($avgSales / $avgReceipts) : 150.0);
  $loyalCustomers = max(1, round($todayTraffic * (1 - ($riskPct / 100)) * 0.4));
  $loyaltyRate = max(0, 100 - $riskPct);
  
  // Enhanced segmentation patterns
  $segmentation = [];
  
  if ($todayTraffic > 0 || $avgTraffic > 0) {
    if ($trafficChange > 15) {
      $segmentation[] = 'Growing customer base (+' . round($trafficChange, 1) . '%)';
    } elseif ($trafficChange < -15) {
      $segmentation[] = 'Customer retention focus needed';
    } else {
      $segmentation[] = 'Stable customer segment';
    }
    
    if ($avgPurchaseValue > 200) {
      $segmentation[] = 'Premium customer segment';
    } elseif ($avgPurchaseValue > 100) {
      $segmentation[] = 'Mid-tier customer base';
    } else {
      $segmentation[] = 'Value-conscious shoppers';
    }
    
    // Shift analysis if available
    $morningReceipts = (int)($today['morning_receipt_count'] ?? 0);
    $swingReceipts = (int)($today['swing_receipt_count'] ?? 0);
    $graveyardReceipts = (int)($today['graveyard_receipt_count'] ?? 0);
    $totalShift = $morningReceipts + $swingReceipts + $graveyardReceipts;
    
    if ($totalShift > 0) {
      $morningPct = ($morningReceipts / $totalShift) * 100;
      $swingPct = ($swingReceipts / $totalShift) * 100;
      $graveyardPct = ($graveyardReceipts / $totalShift) * 100;
      
      if ($morningPct >= 50) $segmentation[] = 'Morning-focused customers';
      if ($swingPct >= 40) $segmentation[] = 'After-work shoppers';
      if ($graveyardPct >= 30) $segmentation[] = 'Late-night convenience users';
    }
  } else {
    $segmentation[] = 'Building initial customer data';
    $segmentation[] = 'Early business phase';
  }
  
  // Enhanced purchase patterns
  $purchasePatterns = [];
  
  if ($todayReceipts > 0 || $avgReceipts > 0) {
    $purchasePatterns[] = 'Average ticket: ₱' . number_format($avgPurchaseValue, 2);
    
    if ($salesChange > 10) {
      $purchasePatterns[] = 'Revenue growing (+' . round($salesChange, 1) . '%)';
    } elseif ($salesChange < -10) {
      $purchasePatterns[] = 'Revenue attention needed (' . round($salesChange, 1) . '%)';
    } else {
      $purchasePatterns[] = 'Stable spending behavior';
    }
    
    if ($receiptChange > $salesChange + 5) {
      $purchasePatterns[] = 'More transactions, smaller baskets';
    } elseif ($salesChange > $receiptChange + 5) {
      $purchasePatterns[] = 'Higher value per transaction';
    }
    
    $purchasePatterns[] = number_format($todayReceipts) . ' transactions today';
  } else {
    $purchasePatterns[] = 'Establishing purchase tracking';
    $purchasePatterns[] = 'Setting baseline metrics';
  }
  
  // Enhanced risk indicators
  $riskIndicators = [];
  
  if (!empty($prediction['factors'])) {
    $factors = is_string($prediction['factors']) ? json_decode($prediction['factors'], true) : $prediction['factors'];
    if (is_array($factors)) {
      $riskIndicators = array_slice($factors, 0, 4); // Limit to 4 factors
    }
  }
  
  // Add calculated risk indicators
  if (empty($riskIndicators)) {
    if ($riskPct >= 70) {
      $riskIndicators[] = 'High churn probability detected';
    } elseif ($riskPct >= 40) {
      $riskIndicators[] = 'Moderate retention attention needed';
    } else {
      $riskIndicators[] = 'Good customer retention indicators';
    }
    
    if ($receiptChange < -20) {
      $riskIndicators[] = 'Transaction volume declining';
    } elseif ($salesChange < -20) {
      $riskIndicators[] = 'Revenue trend requires attention';
    }
    
    if (count($data14Days) < 7) {
      $riskIndicators[] = 'Limited historical data available';
    }
  }
  
  // Enhanced trend analysis
  $trendDirection = 'Stable';
  $trendPatterns = [];
  
  $positiveIndicators = 0;
  $negativeIndicators = 0;
  
  if ($receiptChange > 5) $positiveIndicators++;
  if ($salesChange > 5) $positiveIndicators++;
  if ($trafficChange > 5) $positiveIndicators++;
  
  if ($receiptChange < -5) $negativeIndicators++;
  if ($salesChange < -5) $negativeIndicators++;
  if ($trafficChange < -5) $negativeIndicators++;
  
  if ($positiveIndicators >= 2) {
    $trendDirection = 'Positive Growth';
    $trendPatterns[] = 'Multiple metrics improving';
    $trendPatterns[] = 'Business momentum building';
  } elseif ($negativeIndicators >= 2) {
    $trendDirection = 'Needs Attention';
    $trendPatterns[] = 'Multiple metrics declining';
    $trendPatterns[] = 'Intervention recommended';
  } else {
    $trendDirection = 'Mixed Signals';
    $trendPatterns[] = 'Some metrics up, others stable';
  }
  
  // Add specific trend insights
  if (abs($receiptChange) > 10) {
    $direction = $receiptChange > 0 ? 'increased' : 'decreased';
    $trendPatterns[] = "Transactions {$direction} " . abs(round($receiptChange, 1)) . '%';
  }
  
  if (abs($salesChange) > 10) {
    $direction = $salesChange > 0 ? 'increased' : 'decreased';
    $trendPatterns[] = "Revenue {$direction} " . abs(round($salesChange, 1)) . '%';
  }
  
  if (count($data14Days) >= 7) {
    $trendPatterns[] = count($data14Days) . ' days of data analyzed';
  } else {
    $trendPatterns[] = 'Building 14-day analysis baseline';
  }
  
  json_ok([
    'loyalCustomers' => $loyalCustomers,
    'avgPurchaseValue' => round($avgPurchaseValue, 2),
    'loyaltyRate' => round($loyaltyRate, 1),
    'segmentation' => $segmentation,
    'purchasePatterns' => $purchasePatterns,
    'riskIndicators' => $riskIndicators,
    'riskLevel' => $riskLevel,
    'riskPercentage' => round($riskPct, 1),
    'trendDirection' => $trendDirection,
    'trendPatterns' => $trendPatterns
  ]);
  
} catch (Throwable $e) {
  error_log('Customer Insights API Error: ' . $e->getMessage());
  json_error('Unable to load customer insights. Please try again.', 500);
}
?>