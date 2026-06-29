import React from 'react';
import Header from '../components/Header';
import Footer from '../components/Footer';
import '../styles/Home.css';

function Home() {
  return (
    <div className="home-page">
      <Header />
      <main className="app-container">
        <div className="content">
          <h1>Welcome to the Happiness Audit Application</h1>
          <p>This is your home page. Get started by exploring the application features.</p>
        </div>
      </main>
      <Footer />
    </div>
  );
}

export default Home;
