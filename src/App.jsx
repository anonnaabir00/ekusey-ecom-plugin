import { useState, useEffect } from 'react';
import Dashboard from './pages/Dashboard';
import Products from './pages/Products';
import Affiliates from './pages/Affiliates';
import Settings from './pages/Settings';

/**
 * Parse the "path" query param from the current URL.
 * WordPress admin sub-menus pass ?page=ekusey-ecom&path=products
 */
function getPath() {
  const params = new URLSearchParams(window.location.search);
  return params.get('path') || 'dashboard';
}

const PAGES = {
  dashboard: Dashboard,
  products: Products,
  affiliates: Affiliates,
  settings: Settings,
};

export default function App() {
  const [currentPath, setCurrentPath] = useState(getPath);

  // Listen for popstate so browser back/forward works.
  useEffect(() => {
    const handler = () => setCurrentPath(getPath());
    window.addEventListener('popstate', handler);
    return () => window.removeEventListener('popstate', handler);
  }, []);

  const Page = PAGES[currentPath] || Dashboard;

  return (
    <div className="ekusey-app">
      <Page />
    </div>
  );
}
