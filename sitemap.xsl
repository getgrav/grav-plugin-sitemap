<xsl:stylesheet version="1.0"
 xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
 xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
 exclude-result-prefixes="s"
>
 <xsl:template match="/">
  <html>
    <body>
      <h2>Sitemap</h2>
      <table border="1">
        <tr bgcolor="#9acd32">
          <th>Location</th>
          <th>Last Modified</th>
          <th>Update Frequency</th>
          <th>Priority</th>
        </tr>
        <xsl:for-each select="s:urlset/s:url">
          <tr>
            <td><xsl:value-of select="s:loc"/></td>
            <td><xsl:value-of select="s:lastmod"/></td>
            <td><xsl:value-of select="s:changefreq"/></td>
            <td><xsl:value-of select="s:priority"/></td>
          </tr>
        </xsl:for-each>
      </table>
    </body>
  </html>
 </xsl:template>
</xsl:stylesheet>