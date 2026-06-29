import React from 'react';
import '../styles/Footer.css';

function Footer() {
  return (
    <footer className="site-footer">
      <div className="footer-inner">
        <p>© {new Date().getFullYear()} Happiness Audit. All rights reserved.</p>
      </div>
    </footer>
  );
}

export default Footer;
