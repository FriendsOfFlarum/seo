import Page from 'flarum/common/components/Page';

export default class SSLPage extends Page {
  view() {
    return (
      <div>
        <h2>Why do I need an secure connection?</h2>
        <p>
          Safety and privacy awareness on the web is on the rise. <b>Almost every</b> developer/website owner want their website safe to use for their
          visitors so they won't need to be afraid that their data will be compromised by hackers or website-impersonators.
        </p>

        <h4>
          Search engines <i className="fas fa-heart"></i> secure connections
        </h4>
        <p>
          When you do not have a secure connection to your website, search engines will rank your site much lower then other sites, or even won't
          index it.
        </p>

        <p>When you have SSL available, your website will get an higher rank and will be indexed.</p>

        <h4>What is SSL or TLS?</h4>
        <p>
          The most people know <b>https</b> that's used for secure connections as SSL: <i>Secure Sockets Layer</i>. Officially it's called TLS:{' '}
          <i>Transport Layer Security</i>. This method is used to create a secure connection to your webserver what will prevent attackers or other
          webservers to impersonate your website and keep your visitors safe. The SSL connection will be broken if that happens and the visitors
          browsers will warning the user that's it's not trusted.
        </p>

        <h4>How to add SSL to your website?</h4>
        <p>
          For people who are using a webhosting, the most common way is to enter the webhosting panel, go to the hosting-settings of your website and
          click SSL. You can follow the steps to add SSL to your website. The most webhosting companies nowadays are supporting the popular
          certificate issuer{' '}
          <a href="https://letsencrypt.org/" target="_blank">
            {' '}
            Let's Encrypt <i className="fas fa-external-link-alt"></i>
          </a>
          .
        </p>

        <h4>Okay, I added SSL!</h4>
        <p>
          Great! Now, change your <b>config.php</b> and change the <b>'url'</b> to <b>https</b>!
        </p>

        <h4>What if I do not want to add SSL?</h4>
        <p>
          In that case, you can uninstall this extension as search engines <b>won't index your forum</b> or rank them far below other sites due safety
          reasons.
        </p>
      </div>
    );
  }
}
