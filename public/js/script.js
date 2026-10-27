const ctx = document.getElementById('productionChart').getContext('2d');

console.log("✅ JS file loaded!");
alert("Hello from script.js!");

const productionChart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['Branch 1', 'Branch 2', 'Branch 3'],
    datasets: [{
      label: 'Chicken Qty',
      data: [120, 90, 150], // sample data, later replace with Flask API
      backgroundColor: ['#4CAF50', '#2196F3', '#FF9800']
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top' },
      title: { display: true, text: 'Production by Branch' }
    }
  }
});
