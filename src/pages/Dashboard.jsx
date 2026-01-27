export default function Dashboard() {
  return (
    <div>
      <h1>Dashboard</h1>

      <div className="ekusey-grid">
        <div className="ekusey-card ekusey-stat">
          <div className="value">--</div>
          <div className="label">Total Products</div>
        </div>

        <div className="ekusey-card ekusey-stat">
          <div className="value">--</div>
          <div className="label">Active Brands</div>
        </div>

        <div className="ekusey-card ekusey-stat">
          <div className="value">--</div>
          <div className="label">Affiliate Orders</div>
        </div>

        <div className="ekusey-card ekusey-stat">
          <div className="value">--</div>
          <div className="label">Pending Commissions</div>
        </div>
      </div>

      <div className="ekusey-card">
        <h2>Quick Actions</h2>
        <p>Use the sub-menu pages to manage products, affiliates, and settings.</p>
      </div>
    </div>
  );
}
