import React, { useState, useRef, useEffect } from 'react';
import '../styles/Header.css';

function Header() {
  const [open, setOpen] = useState(false);
  const menuRef = useRef(null);

  useEffect(() => {
    function handleClick(e) {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  return (
    <header className="site-header">
      <div className="header-inner">
        <div className="brand">🎯 Happiness Audit</div>
        <nav className="nav">
          <div className="menu" ref={menuRef}>
            <button
              className="menu-button"
              onClick={() => setOpen((s) => !s)}
              aria-haspopup="true"
              aria-expanded={open}
            >
              Menu ▾
            </button>
            {open && (
              <ul className="menu-list" role="menu">
                <li role="menuitem"><a href="/">Home</a></li>
                <li role="menuitem"><a href="/admin">Admin</a></li>
                <li role="menuitem"><a href="/about">About</a></li>
                <li role="menuitem"><a href="/survey">Survey</a></li>
                <li role="menuitem"><a href="/contact">Contact</a></li>
              </ul>
            )}
          </div>
        </nav>
      </div>
    </header>
  );
}

export default Header;
