<xsl:stylesheet version="1.0"
 xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
 xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
 exclude-result-prefixes="s"
>
 <xsl:template match="/">
  <html>
      <head>
        <meta name="robots" content="noindex" />
        <title>
          XML Sitemap
        </title>
        <style type="text/css">
            @import url('//cdn.jsdelivr.net/pure/0.6.0/base-min.css');
            @import url('//cdn.jsdelivr.net/pure/0.6.0/pure-min.css');
            @import url('//cdn.jsdelivr.net/pure/0.6.0/grids-responsive-min.css');
            @import url('//fonts.googleapis.com/css?family=Raleway:100,300,400,700,900,100italic,300italic,400italic,700italic,900italic');
            .font_smooth {
              font-smooth: auto;
              text-shadow: 0 0 1px rgba(0, 0, 0, 0.2);
              text-rendering: auto;
              -webkit-font-smoothing: antialiased;
              -webkit-text-size-adjust: 100%
            }
            html {
              font-smooth: auto;
              text-shadow: 0 0 1px rgba(0, 0, 0, 0.2);
              text-rendering: auto;
              -webkit-font-smoothing: antialiased;
              -webkit-text-size-adjust: 100%;
              background-color: #fff
            }
            body {
              font-family: 'Raleway', sans-serif;
              font-size: 20px;
              line-height: 1.8em;
              letter-spacing: 0;
              text-align: left;
              color: #333
            }
            body {
              overflow: auto;
              padding: 20px
            }
            .clear {
              clear: both;
              float: none
            }
            a,
            a:link,
            a:visited {
              text-decoration: none;
              border-bottom: dotted 1px #333;
              color: #333
            }
            h1,h2,h3,h4,h5,h6 {
              font-family: Raleway;
              font-weight: 300;
              line-height: 1.2em;
              letter-spacing: 0px;
              color: #000
            }
            table {
              margin: 0 auto;
            }
            th {
              border: solid 1px #cbcbcb !important;
              text-align: center;
              background: #fff
            }
        </style>
      </head>
    <body>
      <table class="pure-table pure-table-striped" border="0">
        <thead>
          <tr>
            <th colspan="5">Sitemap</th>
          </tr>
          <tr>
            <th></th>
            <th>Location</th>
            <th>Last Modified</th>
            <th>Update Frequency</th>
            <th>Priority</th>
          </tr>
        </thead>
        <tfoot>
        </tfoot>
        <tbody>
          <xsl:for-each select="s:urlset/s:url">
            <xsl:sort select="s:loc" />
            <tr>
              <xsl:variable name="loc"><xsl:value-of select="s:loc"/></xsl:variable>
              <xsl:variable name="pno"><xsl:value-of select="position()"/></xsl:variable>
              <td><xsl:value-of select="$pno"/></td>
              <td><a href="{$loc}"><xsl:value-of select="s:loc"/></a></td>
              <td><xsl:value-of select="s:lastmod"/></td>
              <td><xsl:value-of select="s:changefreq"/></td>
              <td><xsl:value-of select="s:priority"/></td>
            </tr>
          </xsl:for-each>
        </tbody>
      </table>
    </body>
  </html>
 </xsl:template>
</xsl:stylesheet>
